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
    /*
    if (!\Drupal::moduleHandler()->moduleExists('salesforce_soap')) {
      salesforce_set_message('Enable Salesforce SOAP to process deleted records');
      return;
    }
    module_load_include('inc', 'salesforce_soap');
    $soap = new SalesforceSoapPartner($sfapi);
    */
    foreach (array_reverse(salesforce_mapping_get_mapped_objects()) as $type) {
      $last_delete_sync = \Drupal::state()->get('salesforce_pull_delete_last_' . $type, REQUEST_TIME);
      $now = time();
      // SOAP getDeleted() restraint: startDate must be at least one minute
      // greater than endDate.
      $now = $now > $last_delete_sync + 60 ? $now : $now + 60;
      $last_delete_sync_sf = gmdate('Y-m-d\TH:i:s\Z', $last_delete_sync);
      $now_sf = gmdate('Y-m-d\TH:i:s\Z', $now);
      //$deleted = $soap->getDeleted($type, $last_delete_sync_sf, $now_sf);
      $deleted = $sfapi->apiCall(
        "sobjects/$type/deleted/?start=$last_delete_sync_sf&end=$now_sf",
        [],
        'GET'
      );
      // Cast $deleted as object since REST is returning an array instead of
      // the object the SOAP client apparantly does
      $deleted = (object) $deleted;

      if (!empty($deleted->deletedRecords)) {
        $sf_mappings = salesforce_mapping_load_multiple(
          ['salesforce_object_type' => $type]
        );
        foreach ($deleted->deletedRecords as $record) {
          try {
            $mapped_object = salesforce_mapped_object_load_by_sfid($record['id']);
            $entity = \Drupal::entityTypeManager()
              ->getStorage($mapped_object->entity_type)
              ->load($mapped_object->entity_id);
            foreach ($sf_mappings as $sf_mapping) {
              if ($sf_mapping->checkTriggers([SALESFORCE_MAPPING_SYNC_SF_DELETE])) {
                // Delete mapping object.
                /*
                 * Not sure what this code does
                $transaction = db_transaction();
                $map_entity = \Drupal::entityTypeManager()
                  ->getStorage('salesforce_mapped_object')
                  ->load($mapped_object->salesforce_mapped_object_id);
                $map_entity->delete();
                $map_entity = \Drupal::entityTypeManager()
                  ->getStorage($sf_mapping->drupal_entity_type)
                  ->load($mapped_object->entity_id);
                $map_entity->delete();
                */
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
                }
              }
            }
            $mapped_object->delete();
          }
          catch (Exception $e) {
            \Drupal::logger('Salesforce Pull')->notice(
              'No mapped object exists for Salesforce Object ID: %sfid',
              [
                '%sfid' => $record->id,
              ]
            );
          }
        }
      }
      \Drupal::state()->set('salesforce_pull_delete_last_' . $type, REQUEST_TIME);
    }
  }
}
