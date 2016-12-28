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

  public function __construct(RestClient $sfapi) {
    $this->sfapi = $sfapi;
    $this->queue = \Drupal::queue('cron_salesforce_pull');
    $this->organizeMappings();
  }

  /**
   * Pull updated records from Salesforce and place them in the queue.
   *
   * Executes a SOQL query based on defined mappings, loops through the results,
   * and places each updated SF object into the queue for later processing.
   */
  public static function getUpdatedRecords() {
    // Avoid overloading the processing queue and pass this time around if it's
    // over a configurable limit.
    if ($this->queue->numberOfItems() > \Drupal::state()->get('salesforce_pull_max_queue_size', 100000)) {
      // @TODO add admin / logging alert here. This is a critical condition. When our queue is maxed out, pulls will be completely blocked.
      return;
    }

    // Iterate over each field mapping to determine our query parameters.
    foreach ($this->mappings as $mapping) {
      $results = $this->doSfoQuery($mapping);

      if (!isset($results['errorCode'])) {
        // Write items to the queue.
        foreach ($results['records'] as $result) {
          $this->queue->createItem($result);
        }
        $this->handleLargeRequests($results);
        \Drupal::state()->set('salesforce_pull_last_sync_' . $sf_object_type, REQUEST_TIME);
      }
      else {
        \Drupal::logger('Salesforce Pull')->error('%code:%msg', [
          '%code' => $results['errorCode'],
          '%msg' => $results['message'],
        ]);
      }
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
    $this->mappings = [];
    $this->pull_fields = [];
    foreach(salesforce_mapping_load_multiple() as $mapping) {
      $this->pull_fields[$mapping->getSalesforceObjectType()] += $this->getFieldArray($mapping);
      $this->mappings[] = $mapping;
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
    $soql = new SelectQuery($type);

    // Convert field mappings to SOQL.
    $soql->fields = ['Id', $mapping->get('pull_trigger_date')];
    $mapped_fields = 
      $this->pull_fields[$mapping->get('salesforce_record_type')]);
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
    $sf_record_type = $mapping->get('salesforce_record_type');
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
  protected function handleLargeRequests(array $results) {
    $version_path = parse_url($sfapi->getApiEndPoint(), PHP_URL_PATH);
    $next_records_url = isset($results['nextRecordsUrl']) ?
      str_replace($version_path, '', $results['nextRecordsUrl']) :
      FALSE;
    while ($next_records_url) {
      $new_result = $this->sfapi->apiCall($next_records_url);
      if (!isset($new_result['errorCode'])) {
        // Write items to the queue.
        foreach ($new_result['records'] as $result) {
          $this->queue->createItem($result);
        }
      }
      $next_records_url = isset($new_result['nextRecordsUrl']) ?
        str_replace($version_path, '', $new_result['nextRecordsUrl']) : FALSE;
    }
  }

}
