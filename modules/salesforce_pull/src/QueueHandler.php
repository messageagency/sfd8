<?php

namespace Drupal\salesforce_pull;

use Drupal\Core\Queue\QueueInterface;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce\Exception;
use Drupal\salesforce\LoggingTrait;
use Psr\Log\LogLevel;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;

/**
 * Handles pull cron queue set up.
 *
 * @see \Drupal\salesforce_pull\QueueHandler
 */

class QueueHandler {

  use LoggingTrait;

  protected $sfapi;
  protected $queue;
  protected $mappings;
  protected $pull_fields;
  protected $event_dispatcher;

  protected function __construct(RestClient $sfapi, array $mappings, QueueInterface $queue, ContainerAwareEventDispatcher $event_dispatcher) {
    $this->sfapi = $sfapi;
    $this->queue = $queue;
    $this->event_dispatcher = $event_dispatcher;
    $this->mappings = $mappings;
    $this->pull_fields = [];
    $this->organizeMappings();
  }

  /**
   * Chainable instantiation method for class
   *
   * @param object
   *   RestClient object
   * @param array
   *   Arry of SalesforceMapping objects
   *
   * @return QueueHandler
   */
  public static function create(RestClient $sfapi, array $mappings, QueueInterface $queue, ContainerAwareEventDispatcher $event_dispatcher) {
    return new QueueHandler($sfapi, $mappings, $queue, $event_dispatcher);
  }

  /**
   * Pull updated records from Salesforce and place them in the queue.
   *
   * Executes a SOQL query based on defined mappings, loops through the results,
   * and places each updated SF object into the queue for later processing.
   *
   * @return boolean
   */
  public function getUpdatedRecords() {
    // Avoid overloading the processing queue and pass this time around if it's
    // over a configurable limit.
    if ($this->queue->numberOfItems() > $this->stateGet('salesforce_pull_max_queue_size', 100000)) {
      $this->log(
        'Salesforce Pull',
        LogLevel::ALERT,
        'Pull Queue contains %noi items, exceeding the max size of %max items. Pull processing will be blocked until the number of items in the queue is reduced to below the max size.',
        [
          '%noi' => $this->queue->numberOfItems(),
          '%max' => $this->stateGet('salesforce_pull_max_queue_size', 100000),
        ]
      );
      return false;
    }

    // Iterate over each field mapping to determine our query parameters.
    foreach ($this->mappings as $mapping) {
      $results = $this->doSfoQuery($mapping);
      $this->insertIntoQueue($mapping, $results->records());
      $this->handleLargeRequests($mapping, $results);
      $this->stateSet(
        'salesforce_pull_last_sync_' . $mapping->getSalesforceObjectType(),
        $this->requestTime()
      );
    }
    return true;
  }

  /**
   * Iterates over the mappings and mergeds the pull fields array with object's
   * array of pull fields to form a set of unique fields to pull.
   */
  protected function organizeMappings() {
    foreach($this->mappings as $mapping) {
      $this->pull_fields[$mapping->getSalesforceObjectType()] =
        (!empty($this->pull_fields[$mapping->getSalesforceObjectType()])) ?
          $this->pull_fields[$mapping->getSalesforceObjectType()] + $mapping->getPullFieldsArray() :
          $mapping->getPullFieldsArray();
    }
  }

  /**
   * Perform the SFO Query on each SF Object type with concolidated array of fields
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
    $soql->fields = ['Id', $mapping->get('pull_trigger_date')];
    $mapped_fields = $this->pull_fields[$mapping->getSalesforceObjectType()];
    foreach ($mapped_fields as $field) {
      $soql->fields[] = $field;
    }

    // If no lastupdate, get all records, else get records since last pull.
    $sf_last_sync = $this->stateGet('salesforce_pull_last_sync_' . $mapping->getSalesforceObjectType(), NULL);
    if ($sf_last_sync) {
      $last_sync = gmdate('Y-m-d\TH:i:s\Z', $sf_last_sync);
      $soql->addCondition($mapping->get('pull_trigger_date'), $last_sync, '>');
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
      $this->watchdogException($e);
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
   if ($results->nextRecordsUrl() != null) {
     $version_path = $this->parseUrl();
     try {
       $new_result = $this->sfapi->apiCall(
         str_replace($version_path, '', $results->nextRecordsUrl()));
       $this->insertIntoQueue($mapping, $new_result->records());
       $this->handleLargeRequests($mapping, $new_result);
     }
     catch (\Exception $e) {
       $this->watchdogException($e);
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
        Pull Queue Enqueue Event
        $this->queue->createItem(new PullQueueItem($record, $mapping));
      }
    }
    catch (\Exception $e) {
      $this->watchdogException($e);
    }
  }

  /**
   * Wrapper for Drupal::state()->get()
   */
  protected function stateGet($name, $value) {
    return \Drupal::state()->get($name, $value);
  }

  /**
   * Wrapper for Drupal::state()->set()
   */
  protected function stateSet($name, $value) {
    return \Drupal::state()->set($name, $value);
  }

  /**
   * Wrapper for Drupal::state()->set()
   */
  protected function parseUrl() {
    parse_url($this->sfapi->getApiEndPoint(), PHP_URL_PATH);
  }

  /**
   * Wrapper for \Drupal::request()
   */
  protected function requestTime() {
    return \Drupal::request()->server->get('REQUEST_TIME');
  }
}
