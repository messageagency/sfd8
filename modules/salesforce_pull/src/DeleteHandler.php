<?php

namespace Drupal\salesforce_pull;

use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce\Exception;
/**
 * Handles pull cron deletion of Drupal entities based onSF mapping settings.
 *
 * @see \Drupal\salesforce_pull\DeleteHandler
 */

class DeleteHandler {
  public function __construct() {}

  /**
   * Process deleted records from salesforce.
   */
  public static function processDeletedRecords(RestClient $sfapi) {
    // @TODO Add back in SOAP, and use autoloading techniques
    foreach (array_reverse(salesforce_mapping_get_mapped_sobject_types()) as $type) {
      $last_delete_sync = \Drupal::state()->get('salesforce_pull_delete_last_' . $type, REQUEST_TIME);
      $now = time();
      // getDeleted() restraint: startDate must be at least one minute
      // greater than endDate.
      $now = $now > $last_delete_sync + 60 ? $now : $now + 60;
      $last_delete_sync_sf = gmdate('Y-m-d\TH:i:s\Z', $last_delete_sync);
      $now_sf = gmdate('Y-m-d\TH:i:s\Z', $now);
      $deleted = $sfapi->getDeleted($type, $last_delete_sync_sf, $now_sf);
      $this->handleDeletedRecords($deleted);
      \Drupal::state()->set('salesforce_pull_delete_last_' . $type, REQUEST_TIME);
    }
  }

  protected function handleDeletedRecords(array $deleted, $type) {
    if (empty($deleted['deletedRecords'])) {
      return;
    }
    $deleted = (object) $deleted;

    try {
      $sf_mappings = salesforce_mapping_load_multiple(
        ['salesforce_object_type' => $type]
      );
    }
    catch (Exception $e) {
      // No mappings found. Quit now.
      return;
    }

    foreach ($deleted->deletedRecords as $record) {
      $this->handleDeletedRecord($record, $type);
    }
  }
  
  protected function handleDeletedRecord($record, $type) {
    try {
      $mapped_objects = salesforce_mapped_object_load_by_sfid($record->id);
    }
    catch (Exception $e) {
      // @TODO do we need a log entry for every object which gets deleted and isn't mapped to Drupal?
      \Drupal::logger('Salesforce Pull')->notice(
        'No mapped object exists for Salesforce Object ID: %sfid',
        [
          '%sfid' => $record->id,
        ]
      );
      return;
    }

    foreach ($mapped_objects as $mapped_object) {
      try {
        $entity = \Drupal::entityTypeManager()
          ->getStorage($mapped_object->entity_type)
          ->load($mapped_object->entity_id);
        if (!$entity) {
          throw new Exception();
        }
      }
      catch (Exception $e) {
        // No mapped entity found for the mapped object. Just delete the mapped object and continue.
        \Drupal::logger('Salesforce Pull')->notice(
          'No entity found for ID %id associated with Salesforce Object ID: %sfid ',
          [
            '%id' => $mapped_object->entity_id,
            '%sfid' => $record->id,
          ]
        );
        $mapped_object->delete();
        continue;
      }

      try {
        $sf_mapping = salesforce_mapping_load($mapped_object->get('salesforce_mapping'));
      }
      catch (Exception $e) {
        \Drupal::logger('Salesforce Pull')->notice(
          'No mapping exists for mapped object %id with Salesforce Object ID: %sfid',
          [
            '%id' => $mapped_object->id(),
            '%sfid' => $record->id,
          ]
        );
        // @TODO should we delete a mapped object whose parent mapping no longer exists? Feels like someone else's job.
        // $mapped_object->delete();
        continue;
      }

      if (!$sf_mapping->checkTriggers([SALESFORCE_MAPPING_SYNC_SF_DELETE])) {
        continue;
      }

      try {
        $entity->delete();
        \Drupal::logger('Salesforce Pull')->notice(
          'Deleted entity %label with ID: %id associated with Salesforce Object ID: %sfid',
          [
            '%label' => $entity->label(),
            '%id' => $mapped_object->entity_id,
            '%sfid' => $record->id,
          ]
        );
      }
      catch (Exception $e) {
        \Drupal::logger('Salesforce Pull')->error($e);
        // If mapped entity couldn't be deleted, do not delete the mapped object either.
        continue;
      }

      $mapped_object->delete();
    }
  }
}
