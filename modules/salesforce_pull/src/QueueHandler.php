<?php

namespace Drupal\salesforce_pull;

use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce\Exception;
/**
 * Handles pull cron queue set up.
 *
 * @see \Drupal\salesforce_pull\QueueHandler
 */

class QueueHandler {
  protected $sfapi;
  protected $queue;

  public function __construct(RestClient $sfapi) {
    $this->sfapi = $sfapi;
    $this->queue = \Drupal::queue('cron_salesforce_pull');
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
      return;
    }

    list($mappings, $pull_fields) = $this->organizeMappings();

    // Iterate over each field mapping to determine our query parameters.
    foreach ($mappings as $sf_object_type => $mapping) {
      $mapped_fields = array_merge(...$pull_fields[$sf_object_type]);
      $mapped_record_types = [];
      $sf_record_type = $mapping->get('salesforce_record_type');
      if (
        !empty($mapped_fields)
        && !empty($sf_record_type)
        && $sf_record_type != SALESFORCE_MAPPING_DEFAULT_RECORD_TYPE
      ) {
        $mapped_record_types[$sf_record_type] = $sf_record_type;
        // Add the RecordTypeId field so we can use it when processing the
        // queued SF objects.
        $mapped_fields['RecordTypeId'] = 'RecordTypeId';
      }
      $results = $this->doSfoQuery($mapped_fields, $mapped_record_types, $sf_object_type);

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
    // Grab all mapping and sort them by $mapping->salesforce_object_type.
    //$mappings = salesforce_mapping_load_multiple();
    /* Need to sort?
    usort(
      $mappings,
      function($a, $b) {
        return strcmp($a->get('salesforce_object_type'), $b->get('salesforce_object_type'));
      });
    */
    $org_mappings = [];
    $pull_fields_array = [];
    foreach(salesforce_mapping_load_multiple() as $mapping) {
      $pull_fields_array[$mapping->getSalesforceObjectType()][] = $this->getFieldArray($mapping);
      if (!array_key_exists($mapping->getSalesforceObjectType(), $org_mappings)) {
         $org_mappings[$mapping->getSalesforceObjectType()] = $mapping;
      }
    }
    return [$org_mappings, $pull_fields_array];
  }

  /**
   * Build array of pulled fields for given mapping
   *
   * @param object
   *   SalesForceMapping object
   *
   * @return array
   *   Array of field smappings
   */
  protected function getFieldArray(SalesforceMapping $mapping) {
    $mapped_fields = [];
    foreach ($mapping->getPullFields() as $field_map) {
      $sf_field = $field_map->get('salesforce_field');
      // Some field map types (Relation) store a collection of SF objects.
      // @TODO: revisit this
      if (is_array($sf_field) && !isset($sf_field['name'])) {
        foreach ($sf_field as $sf_subfield) {
          $mapped_fields[$sf_subfield['name']] = $sf_subfield['name'];
        }
      }
      // The rest of are just a name/value pair.
      else {
        $mapped_fields[$sf_field] = $sf_field;
      }
    }
    return $mapped_fields;
  }

  /**
   * Perform the SFO Query on each SF Object type with concolidated array of fields
   *
   * @param array
   *   Array of fields to pull
   * @param array
   *   RecotrdTypes to restirct query by, if provided
   * @param string
   *   SF object type
   *
   * @return array
   *   Array of field smappings
   */
  protected function doSfoQuery(array $mapped_fields, array $mapped_record_type, $type) {
    // @TODO figure out the new way to build the query.
    $soql = new SelectQuery($type);
    // Convert field mappings to SOQL.
    $soql->fields = ['Id', 'LastModifiedDate'];
    foreach ($mapped_fields as $field) {
      // Don't add the Id field to the SOQL query.
      if ($field == 'Id') {
        continue;
      }
      $soql->fields[] = $field;
    }

    // If no lastupdate, get all records, else get records since last pull.
    $sf_last_sync = \Drupal::state()->get('salesforce_pull_last_sync_' . $type, NULL);
    if ($sf_last_sync) {
      $last_sync = gmdate('Y-m-d\TH:i:s\Z', $sf_last_sync);
      $soql->addCondition('LastModifiedDate', $last_sync, '>');
    }

    // If Record Type is specified, restrict query.
    if (count($mapped_record_types) > 0) {
      $soql->addCondition('RecordTypeId', $mapped_record_types, 'IN');
    }

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
