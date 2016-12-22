<?php

/**
 * @file
 * Contains Drupal\salesforce_pull\Plugin\QueueWorker\PullBase.php
 */

namespace Drupal\salesforce_pull\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides base functionality for the Salesforce Pull Queue Workers.
 */
abstract class PullBase extends QueueWorkerBase {

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * Creates a new PullBase object.
   */
  public function __construct() {
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($sf_object) {
    // Get Mapping.
    $mapping_conditions = [
      'salesforce_object_type' => $sf_object['attributes']['type'],
    ];
    if (isset($sf_object['RecordTypeId']) && $sf_object['RecordTypeId'] != SALESFORCE_MAPPING_DEFAULT_RECORD_TYPE) {
      $mapping_conditions['salesforce_record_type'] = $sf_object['RecordTypeId'];
    }

    $sf_mappings = salesforce_mapping_load_multiple($mapping_conditions);

    foreach ($sf_mappings as $sf_mapping) {
      // Mapping object exists?
      $mapped_object = salesforce_mapped_object_load_by_sfid($sf_object['Id']);
      if ($mapped_object && in_array(SALESFORCE_MAPPING_SYNC_SF_UPDATE, $sf_mapping->sync_triggers)) {
        try {
          $entity = \Drupal::entityTypeManager()
            ->getStorage($mapped_object->entity_type_id->value)
            ->load($mapped_object->entity_id->value);

          // Flag this entity as having been processed. This does not persist,
          // but is used by salesforce_push to avoid duplicate processing.
          $entity->salesforce_pull = TRUE;
          // TODO fix the following. See line 53.
          $entity_updated = isset($entity->updated) ? $entity->updated : $mapped_object->entity_updated;

          $sf_object_updated = strtotime($sf_object[$sf_mapping->get('pull_trigger_date')]);
          if ($sf_object_updated > $entity_updated) {
            // Set fields values on the Drupal entity.
            $mapped_object->pull($sf_object, $entity);

            // Update mapping object.
            $mapped_object->entity_update = REQUEST_TIME;
            \Drupal::logger('Salesforce Pull')->notice(
              'Updated entity %label associated with Salesforce Object ID: %sfid',
              [
                '%label' => $entity->label(),
                '%sfid' => $sf_object['Id'],
              ]
            );

          }
        }
        catch (Exception $e) {
          $message = t('Failed to update entity %label from Salesforce object %sfobjectid. Error: %msg',
            [
              '%label' => $wrapper->label(),
              '%sfobjectid' => $sf_object['Id'],
              '%msg' => $e->getMessage(),
            ]
          );
          \Drupal::logger('Salesforce Pull')->error($message);
          salesforce_set_message($message, 'error', FALSE);
        }
      }
      else {
        if (in_array(SALESFORCE_MAPPING_SYNC_SF_CREATE, $sf_mapping->sync_triggers)) {
          try {
            // Create entity from mapping object and field maps.
            $entity_info = entity_get_info($sf_mapping->drupal_entity_type);

            // Define values to pass to entity_create().
            $values = [];
            if (isset($entity_info['entity keys']['bundle']) &&
              !empty($entity_info['entity keys']['bundle'])) {
              $values[$entity_info['entity keys']['bundle']] = $sf_mapping->drupal_bundle;
            }
            else {
              // Not all entities will have bundle defined under entity keys,
              // e.g. the User entity.
              $values[$sf_mapping->drupal_bundle] = $sf_mapping->drupal_bundle;
            }

            // See note above about flag.
            $values['salesforce_pull'] = TRUE;

            // Create entity.
            $entity = entity_create($sf_mapping->drupal_entity_type, $values);

            // Flag this entity as having been processed. This does not persist,
            // but is used by salesforce_push to avoid duplicate processing.
            $entity->salesforce_pull = TRUE;

            $wrapper = entity_metadata_wrapper($sf_mapping->drupal_entity_type, $entity);
            salesforce_pull_map_fields($sf_mapping->field_mappings, $wrapper, $sf_object);
            $wrapper->save();

            // If no id exists, the insert failed.
            list($entity_id) = entity_extract_ids($sf_mapping->drupal_entity_type, $entity);
            if (!$entity_id) {
              throw new Exception('Entity ID not returned, insert failed.');
            }

            // Create mapping object.
            $mapped_object = entity_create('salesforce_mapped_object', [
              'salesforce_id' => $sf_object['Id'],
              'entity_type' => $sf_mapping->drupal_entity_type,
              'entity_id' => $entity_id,
            ]);

            \Drupal::logger('Salesforce Pull')->notice(
              'Created entity %label associated with Salesforce Object ID: %sfid',
              [
                '%label' => $wrapper->label(),
                '%sfid' => $sf_object['Id'],
              ]
            );
          }
          catch (Exception $e) {
            $message = $e->getMessage() . ' ' . t('Processing failed for entity %label associated with Salesforce Object ID: %sfobjectid',
              [
                '%label' => $wrapper->label(),
                '%sfobjectid' => $sf_object['Id'],
              ]
            );
            \Drupal::logger('Salesforce Pull')->error($message);
            salesforce_set_message('There were failures processing data from SalesForce. Please check the error logs.', 'error', FALSE);
          }
        }
      }

      // Save our mapped objects.
      if ($mapped_object) {
        $mapped_object->save();
      }
    }
  }
}
