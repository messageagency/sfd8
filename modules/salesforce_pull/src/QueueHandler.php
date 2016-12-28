<?php

namespace Drupal\salesforce_pull;

use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce\Exception;
/**
 * Handles pull cron queue set up.
 *
 * @see \Drupal\salesforce_pull\QueueHandler
 */

class QueueHandler {
  protected $sfapi;
  protected $queue;
  protected $mappings;
  protected $pull_fields;

  private function __construct(RestClient $sfapi) {
    $this->sfapi = $sfapi;
    $this->queue = \Drupal::queue('cron_salesforce_pull');
    $this->organizeMappings();
  }

  /**
   * Chainable instantiation method for class
   *
   * @param object
   *  RestClient object
   */
  public static function create(RestClient $sfapi) {
    return new QueueHandler($sfapi);
  }


  /**
   * Pull updated records from Salesforce and place them in the queue.
   *
   * Executes a SOQL query based on defined mappings, loops through the results,
   * and places each updated SF object into the queue for later processing.
   */
  public function getUpdatedRecords() {
    // Avoid overloading the processing queue and pass this time around if it's
    // over a configurable limit.
    if ($this->queue->numberOfItems() > \Drupal::state()->get('salesforce_pull_max_queue_size', 100000)) {
      // @TODO add admin / logging alert here. This is a critical condition. When our queue is maxed out, pulls will be completely blocked.
      return;
    }

    // Iterate over each field mapping to determine our query parameters.
    foreach ($this->mappings as $mapping) {
      // @TODO: This may need a try-catch? all of the following methods will exception catch themselves
      $results = $this->doSfoQuery($mapping);
      $this->insertIntoQueue($mapping, $results->records());
      $this->handleLargeRequests($mapping, $results);
      \Drupal::state()->set('salesforce_pull_last_sync_' . $sf_object_type, REQUEST_TIME);
    }
  }

  /**
   * Fetches all mappings, sortes them by the SF object type, and adds an
   * array of pull fields to each mappings
   *
   * @return array
   *   Array of array of distinct mappings indexed by SF object type and array
   *   of field mappings
   */
  protected function organizeMappings() {
    $this->mappings = salesforce_mapping_load_multiple();
    $this->pull_fields = [];
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
   * @return array
   *   Array of field smappings
   */
  protected function doSfoQuery(SalesforceMappingInterface $mapping) {
    // @TODO figure out the new way to build the query.
    $soql = new SelectQuery($mapping->getSalesforceObjectType());

    // Convert field mappings to SOQL.
    $soql->fields = ['Id', $mapping->get('pull_trigger_date')];
    $mapped_fields =
      $this->pull_fields[$mapping->get('salesforce_record_type')];
    foreach ($mapped_fields as $field) {
      $soql->fields[] = $field;
    }

    // If no lastupdate, get all records, else get records since last pull.
    $sf_last_sync = \Drupal::state()->get('salesforce_pull_last_sync_' . $type, NULL);
    if ($sf_last_sync) {
      $last_sync = gmdate('Y-m-d\TH:i:s\Z', $sf_last_sync);
      $soql->addCondition($mapping->get('pull_trigger_date'), $last_sync, '>');
    }

    // Add RecordTypeId to mapped fields if it's non-default.
    $sf_record_type = $mapping->getSalesforceObjectType();
    if (!empty($mapped_fields)
    && !empty($sf_record_type)
    && $sf_record_type != SALESFORCE_MAPPING_DEFAULT_RECORD_TYPE) {
      $soql->fields[] = 'RecordTypeId';
      $soql->addCondition('RecordTypeId', $sf_record_type, 'IN');
    }

    $soql->fields = array_unique($soql->fields);

    // Execute query.
    try {
      return $this->sfapi->query($soql);
    }
    catch (Exception $e) {
      \Drupal::logger('Salesforce Pull')->error($e->getMessage());
    }
  }

  /**
   * Handle requests larger than the batch limit (usually 2000).
   *
   * @param array
   *   Original list of results, which includes batched records fetch URL
   */
  protected function handleLargeRequests(SalesforceMappingInterface $mapping, array $results) {
   $version_path = parse_url($sfapi->getApiEndPoint(), PHP_URL_PATH);
   if ($results->nextRecordsUrl != null) {
     try {
       $new_result = $this->sfapi->apiCall(
         str_replace($version_path, '', $results->nextRecordsUrl));
       $this->insertIntoQueue($mapping, $new_result->records);
       $this->handleLargeRequests($mapping, $new_result);
     }
     catch (Exception $e) {
       \Drupal::logger('Salesforce Pull')->error($e->getMessage());
     }
   }
  }

  /**
   * Inserts results into queue
   *
   * @param object
   *   Result set
   */
  protected function insertIntoQueue(SalesforceMappingInterface $mapping, array $records) {
    try {
      foreach ($records as $record) {
        $this->queue->createItem(new PullQueueItem($record, $mapping));
      }
    }
    catch (Exception $e) {
      \Drupal::logger('Salesforce Pull')->error($e->getMessage());
    }
  }
}
