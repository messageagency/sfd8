<?php

namespace Drupal\salesforce_pull;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueDatabaseFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Utility\Error;
use Drupal\salesforce\Event\SalesforceErrorEvent;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Event\SalesforceNoticeEvent;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\SObject;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\Event\SalesforceQueryEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Handles pull cron queue set up.
 *
 * @see \Drupal\salesforce_pull\QueueHandler
 */
class QueueHandler {

  const PULL_MAX_QUEUE_SIZE = 100000;

  /**
   * @var \Drupal\salesforce\Rest\RestClientInterface
   */
  protected $sfapi;

  /**
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * @var array of \Drupal\salesforce_mapping\Entity\SalesforceMapping
   */
  protected $mappings;

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * @param RestClientInterface $sfapi
   * @param QueueInterface $queue
   * @param StateInterface $state
   * @param EventDispatcherInterface $event_dispatcher
   */

  public function __construct(RestClientInterface $sfapi, EntityTypeManagerInterface $entity_type_manager, QueueDatabaseFactory $queue_factory, ConfigFactoryInterface $config, EventDispatcherInterface $event_dispatcher, TimeInterface $time) {
    $this->sfapi = $sfapi;
    $this->queue = $queue_factory->get('cron_salesforce_pull');
    $this->config = $config->get('salesforce.settings');
    $this->eventDispatcher = $event_dispatcher;
    $this->time = $time;
    $this->mappings = $entity_type_manager
      ->getStorage('salesforce_mapping')
      ->loadPullMappings();
  }

  /**
   * Pull updated records from Salesforce and place them in the queue.
   *
   * Executes a SOQL query based on defined mappings, loops through the results,
   * and places each updated SF object into the queue for later processing.
   *
   * @param bool $force_pull
   *   Whether to force the queried records to be pulled.
   * @param int $start
   *   Timestamp of starting window from which to pull records. If omitted, use
   *   ::getLastPullTime().
   * @param int $stop
   *   Timestamp of ending window from which to pull records. If omitted, use 
   *   "now"
   *
   * @return bool
   *   TRUE if there was room to add items, FALSE otherwise.
   */
  public function getUpdatedRecords($force_pull = FALSE, $start = 0, $stop = 0) {
    // Avoid overloading the processing queue and pass this time around if it's
    // over a configurable limit.
    $max_size = $this->config->get('pull_max_queue_size', static::PULL_MAX_QUEUE_SIZE);
    if ($max_size && $this->queue->numberOfItems() > $max_size) {
      $message = 'Pull Queue contains %noi items, exceeding the max size of %max items. Pull processing will be blocked until the number of items in the queue is reduced to below the max size.';
      $args = [
        '%noi' => $this->queue->numberOfItems(),
        '%max' => $max_size,
      ];
      $this->eventDispatcher->dispatch(SalesforceEvents::NOTICE, new SalesforceNoticeEvent(NULL, $message, $args));
      return FALSE;
    }

    // Iterate over each field mapping to determine our query parameters.
    foreach ($this->mappings as $mapping) {
      $this->getUpdatedRecordsForMapping($mapping, $force_pull, $start, $stop);
    }
    return TRUE;
  }

  /**
   * Given a mapping and optional timeframe, perform an API query for updated
   * records and enqueue them into the pull queue.
   *
   * @param SalesforceMappingInterface $mapping
   *   The salesforce mapping for which to query.
   * @param bool $force_pull
   *   Whether to force the queried records to be pulled.
   * @param int $start
   *   Timestamp of starting window from which to pull records. If omitted, use
   *   ::getLastPullTime().
   * @param int $stop
   *   Timestamp of ending window from which to pull records. If omitted, use 
   *   "now"
   *
   * @return FALSE | int
   *   Return the number of records fetched by the pull query, or FALSE no
   *   query was executed.
   *
   * @see SalesforceMappingInterface
   */
  public function getUpdatedRecordsForMapping(SalesforceMappingInterface $mapping, $force_pull = FALSE, $start = 0, $stop = 0) {
    if (!$mapping->doesPull()) {
      return FALSE;
    }

    if ($start == 0 && $mapping->getNextPullTime() > $this->time->getRequestTime()) {
      // Skip this mapping, based on pull frequency.
      return FALSE;
    }

    $results = $this->doSfoQuery($mapping);
    if ($results) {
      $this->enqueueAllResults($mapping, $results, $force_pull);
      // @TODO Replace this with a better implementation when available,
      // see https://www.drupal.org/node/2820345, https://www.drupal.org/node/2785211
      $mapping->setLastPullTime($this->time->getRequestTime());
    }
    return $results->size();
  }

  /**
   * Perform the SFO Query for a mapping and its mapped fields.
   *
   * @param SalesforceMappingInterface $mapping
   *   Mapping for which to execute pull
   * @param array $mapped_fields
   *   Fetch only these fields, if given, otherwise fetch all mapped fields.
   * @param int $start
   *   Timestamp of starting window from which to pull records. If omitted, use
   *   ::getLastPullTime().
   * @param int $stop
   *   Timestamp of ending window from which to pull records. If omitted, use 
   *   "now"
   *
   * @return SelectQueryResult
   *   returned result object from Salesforce
   *
   * @see SalesforceMappingInterface
   */
  public function doSfoQuery(SalesforceMappingInterface $mapping, $mapped_fields = [], $start = 0, $stop = 0) {
    // @TODO figure out the new way to build the query.
    // Execute query.
    try {
      $soql = $mapping->getPullQuery($mapped_fields, $start, $stop);
      $this->eventDispatcher->dispatch(
        SalesforceEvents::PULL_QUERY,
        new SalesforceQueryEvent($mapping, $soql)
      );
      return $this->sfapi->query($soql);
    }
    catch (\Exception $e) {
      $message = '%type: @message in %function (line %line of %file).';
      $args = Error::decodeException($e);
      $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e, $message, $args));
    }
  }

  /**
   * Iterates over an entire result set, calling nextRecordsUrl when necessary,
   * and inserts the records into pull queue.
   *
   * @param SalesforceMappingInterface $mapping
   * @param SelectQueryResult $results
   * @param bool $force_pull
   */
  public function enqueueAllResults(SalesforceMappingInterface $mapping, SelectQueryResult $results, $force_pull = FALSE) {
    while (!$this->enqueueResultSet($mapping, $results, $force_pull)) {
      try {
        $results = $this->sfapi->queryMore($results);
      }
      catch (\Exception $e) {
        $message = '%type: @message in %function (line %line of %file).';
        $args = Error::decodeException($e);
        $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e, $message, $args));
        // @TODO do we really want to eat this exception here?
        return;
      }
    }
  }

  /**
   * Enqueue a set of results into pull queue.
   *
   * @param SalesforceMappingInterface $mapping
   *   Mapping object currently being processed
   * @param SelectQueryResult $results
   *   Result record set
   * @param bool $force_pull
   *   Whether to force pull for enqueued items.
   *
   * @return bool
   *   Returns results->done(): TRUE if there are no more results, or FALSE if
   *   there are additional records to be queried.
   */
  public function enqueueResultSet(SalesforceMappingInterface $mapping, SelectQueryResult $results, $force_pull = FALSE) {
    try {
      foreach ($results->records() as $record) {
        // @TODO? Pull Queue Enqueue Event
        $this->enqueueRecord($mapping, $record, $force_pull);
      }
      return $results->done();
    }
    catch (\Exception $e) {
      $message = '%type: @message in %function (line %line of %file).';
      $args = Error::decodeException($e);
      $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e, $message, $args));
    }
  }

  /**
   * Enqueue a single record for pull.
   *
   * @param SalesforceMappingInterface $mapping
   * @param SObject $record
   * @param bool $foce
   */
  public function enqueueRecord(SalesforceMappingInterface $mapping, SObject $record, $force_pull = FALSE) {
    $this->queue->createItem(new PullQueueItem($record, $mapping, $force_pull));
  }

}
