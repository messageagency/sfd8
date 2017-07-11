<?php

namespace Drupal\salesforce_pull;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueDatabaseFactory;
use Drupal\Core\State\StateInterface;
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
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

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

  public function __construct(RestClientInterface $sfapi, EntityTypeManagerInterface $entity_type_manager, QueueDatabaseFactory $queue_factory, StateInterface $state, EventDispatcherInterface $event_dispatcher, TimeInterface $time) {
    $this->sfapi = $sfapi;
    $this->queue = $queue_factory->get('cron_salesforce_pull');
    $this->state = $state;
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
   * @return bool
   *   TRUE if there was room to add items, FALSE otherwise.
   */
  public function getUpdatedRecords() {
    // Avoid overloading the processing queue and pass this time around if it's
    // over a configurable limit.
    if ($this->queue->numberOfItems() > $this->state->get('salesforce.pull_max_queue_size', self::PULL_MAX_QUEUE_SIZE)) {
      $message = 'Pull Queue contains %noi items, exceeding the max size of %max items. Pull processing will be blocked until the number of items in the queue is reduced to below the max size.';
      $args = [
        '%noi' => $this->queue->numberOfItems(),
        '%max' => $this->state->get('salesforce.pull_max_queue_size', self::PULL_MAX_QUEUE_SIZE),
      ];
      $this->eventDispatcher->dispatch(SalesforceEvents::NOTICE, new SalesforceNoticeEvent(NULL, $message, $args));
      return FALSE;
    }

    // Iterate over each field mapping to determine our query parameters.
    foreach ($this->mappings as $mapping) {
      if (!$mapping->doesPull()) {
        continue;
      }
      if ($mapping->getNextPullTime() > $this->time->getRequestTime()) {
        // Skip this mapping, based on pull frequency.
        continue;
      }

      $results = $this->doSfoQuery($mapping);
      if ($results) {
        $this->enqueueAllResults($mapping, $results);
        // @TODO Replace this with a better implementation when available,
        // see https://www.drupal.org/node/2820345, https://www.drupal.org/node/2785211
        $mapping->setLastPullTime($this->time->getRequestTime());
      }
    }
    return TRUE;
  }

  /**
   * Perform the SFO Query for a mapping and its mapped fields.
   *
   * @param SalesforceMappingInterface
   *   Mapping for which to execute pull
   *
   * @return SelectQueryResult
   *   returned result object from Salesforce
   */
  protected function doSfoQuery(SalesforceMappingInterface $mapping) {
    // @TODO figure out the new way to build the query.
    // Execute query.
    try {
      $soql = $mapping->getPullQuery();
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
   */
  public function enqueueAllResults(SalesforceMappingInterface $mapping, SelectQueryResult $results) {
    while (!$this->enqueueResultSet($mapping, $results)) {
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
   * @param SalesforceMappingInterface
   *   Mapping object currently being processed
   * @param array
   *   Result record set
   * @return bool
   *   Returns results->done(): TRUE if there are no more results, or FALSE if
   *   there are additional records to be queried.
   */
  public function enqueueResultSet(SalesforceMappingInterface $mapping, SelectQueryResult $results) {
    try {
      foreach ($results->records() as $record) {
        // @TDOD? Pull Queue Enqueue Event
        $this->enqueueRecord($mapping, $record);
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
   */
  public function enqueueRecord(SalesforceMappingInterface $mapping, SObject $record) {
    $this->queue->createItem(new PullQueueItem($record, $mapping));
  }

}
