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
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\Event\SalesforceQueryEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles pull cron queue set up.
 *
 * @see \Drupal\salesforce_pull\QueueHandler
 */
class QueueHandler {

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

  protected $pull_fields;

  /**
   * @param RestClientInterface $sfapi
   * @param QueueInterface $queue
   * @param StateInterface $state
   * @param EventDispatcherInterface $event_dispatcher
   * @param RequestStack $request_stack
   */

  public function __construct(RestClientInterface $sfapi, EntityTypeManagerInterface $entity_type_manager, QueueDatabaseFactory $queue_factory, StateInterface $state, EventDispatcherInterface $event_dispatcher, RequestStack $request_stack) {
    $this->sfapi = $sfapi;
    $this->queue = $queue_factory->get('cron_salesforce_pull');
    $this->state = $state;
    $this->eventDispatcher = $event_dispatcher;
    $this->request = $request_stack->getCurrentRequest();
    $this->mappings = $entity_type_manager
      ->getStorage('salesforce_mapping')
      ->loadMultiple();
    $this->pull_fields = [];
    $this->organizeMappings();
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
    if ($this->queue->numberOfItems() > $this->state->get('salesforce_pull_max_queue_size', 100000)) {
      $message = 'Pull Queue contains %noi items, exceeding the max size of %max items. Pull processing will be blocked until the number of items in the queue is reduced to below the max size.';
      $args = [
        '%noi' => $this->queue->numberOfItems(),
        '%max' => $this->state->get('salesforce_pull_max_queue_size', 100000),
      ];
      $this->eventDispatcher->dispatch(SalesforceEvents::NOTICE, new SalesforceNoticeEvent(NULL, $message, $args));
      return FALSE;
    }

    // Iterate over each field mapping to determine our query parameters.
    foreach ($this->mappings as $mapping) {
      $results = $this->doSfoQuery($mapping);
      if ($results) {
        $this->insertIntoQueue($mapping, $results->records());
        $this->handleLargeRequests($mapping, $results);
        $this->state->set(
          'salesforce_pull_last_sync_' . $mapping->getSalesforceObjectType(),
          // @TODO Replace this with a better implementation when available,
          // see https://www.drupal.org/node/2820345, https://www.drupal.org/node/2785211
          $this->request->server->get('REQUEST_TIME')
        );
      }
    }
    return TRUE;
  }

  /**
   * Iterates over the mappings and merges the pull fields array with object's
   * array of pull fields to form a set of unique fields to pull.
   */
  protected function organizeMappings() {
    foreach ($this->mappings as $mapping) {
      $this->pull_fields[$mapping->getSalesforceObjectType()] =
        (!empty($this->pull_fields[$mapping->getSalesforceObjectType()])) ?
          $this->pull_fields[$mapping->getSalesforceObjectType()] + $mapping->getPullFieldsArray() :
          $mapping->getPullFieldsArray();
    }
  }

  /**
   * Perform the SFO Query on each SF Object type with concolidated array of fields.
   *
   * @param SalesforceMappingInterface
   *   Mapping for which to execute pull
   *
   * @return SelectQueryResult
   *   returned result object from Salesforce
   */
  protected function doSfoQuery(SalesforceMappingInterface $mapping) {
    // @TODO figure out the new way to build the query.
    $soql = new SelectQuery($mapping->getSalesforceObjectType());

    // Convert field mappings to SOQL.
    $soql->fields = ['Id', $mapping->getPullTriggerDate()];
    $mapped_fields = $this->pull_fields[$mapping->getSalesforceObjectType()];
    foreach ($mapped_fields as $field) {
      $soql->fields[] = $field;
    }

    // If no lastupdate, get all records, else get records since last pull.
    $sf_last_sync = $this->state->get('salesforce_pull_last_sync_' . $mapping->getSalesforceObjectType(), NULL);
    if ($sf_last_sync) {
      $last_sync = gmdate('Y-m-d\TH:i:s\Z', $sf_last_sync);
      $soql->addCondition($mapping->getPullTriggerDate(), $last_sync, '>');
    }

    $soql->fields = array_unique($soql->fields);

    // Execute query.
    try {
      $this->event_dispatcher->dispatch(
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
   * Handle requests larger than the batch limit (usually 2000) recursively.
   *
   * @param SalesforceMappingInterface
   *   Mapping object currently being processed
   * @param SelectQueryResult
   *   Results, which includes batched records fetch URL
   */
  protected function handleLargeRequests(SalesforceMappingInterface $mapping, SelectQueryResult $results) {
    if ($results->nextRecordsUrl() != NULL) {
      $version_path = $this->parseUrl();
      try {
        $new_result = $this->sfapi->apiCall(
          str_replace($version_path, '', $results->nextRecordsUrl()));
        $this->insertIntoQueue($mapping, $new_result->records());
        $this->handleLargeRequests($mapping, $new_result);
      }
      catch (\Exception $e) {
        $message = '%type: @message in %function (line %line of %file).';
        $args = Error::decodeException($e);
        $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e, $message, $args));
      }
    }
  }

  /**
   * Inserts results into queue
   *
   * @param SalesforceMappingInterface
   *   Mapping object currently being processed
   * @param array
   *   Result record set
   */
  protected function insertIntoQueue(SalesforceMappingInterface $mapping, array $records) {
    try {
      foreach ($records as $record) {
        // @TDOD? Pull Queue Enqueue Event
        $this->queue->createItem(new PullQueueItem($record, $mapping));
      }
    }
    catch (\Exception $e) {
      $message = '%type: @message in %function (line %line of %file).';
      $args = Error::decodeException($e);
      $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e, $message, $args));
    }
  }

  /**
   * Wrapper for parse_url()
   */
  protected function parseUrl() {
    parse_url($this->sfapi->getApiEndPoint(), PHP_URL_PATH);
  }

}
