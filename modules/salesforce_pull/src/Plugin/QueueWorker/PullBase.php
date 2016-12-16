<?php

/**
 * @file
 * Contains Drupal\salesforce_pull\Plugin\QueueWorker\PullBase.php
 */

namespace Drupal\salesforce_pull\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;

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
      if (!empty($mapped_object) && $sf_mapping->checkTriggers([SALESFORCE_MAPPING_SYNC_SF_UPDATE])) {
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
            $mapped_object->last_sync = REQUEST_TIME;
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
        if ($sf_mapping->checkTriggers([SALESFORCE_MAPPING_SYNC_SF_CREATE])) {
          try {
            // Create entity from mapping object and field maps.
            $entity_info = \Drupal::entityTypeManager()->getDefinition($sf_mapping->get('drupal_entity_type'));

            // Define values to pass to entity_create().
            $entity_keys = $entity_info->getKeys();
            $values = [];
            if (isset($entity_keys['bundle']) &&
              !empty($entity_keys['bundle'])) {
              $values[$entity_keys['bundle']] = $sf_mapping->get('drupal_bundle');
            }
            else {
              // Not all entities will have bundle defined under entity keys,
              // e.g. the User entity.
              $values[$sf_mapping->get('drupal_bundle')] = $sf_mapping->get('drupal_bundle');
            }

            // See note above about flag.
            $values['salesforce_pull'] = TRUE;

            // Create entity.
            $entity = \Drupal::entityTypeManager()
              ->getStorage($sf_mapping->get('drupal_entity_type'))
              ->create($values);

            // Flag this entity as having been processed. This does not persist,
            // but is used by salesforce_push to avoid duplicate processing.
            $entity->salesforce_pull = TRUE;

            //$wrapper = entity_metadata_wrapper($sf_mapping->drupal_entity_type, $entity);
            $this->mapFields($sf_mapping, $entity, $sf_object);
            $entity->save();

            // If no id exists, the insert failed.
            //list($entity_id) = entity_extract_ids($sf_mapping->drupal_entity_type, $entity);
            if (!$entity->id()) {
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

  /**
   * Map field values.
   *
   * @param object $sf_mapping
   *   Array of field maps.
   * @param object $entity
   *   Entity wrapper object.
   * @param object $sf_object
   *   Object of the Salesforce record.
   * @TODO this should move into SalesforceMapping.php
   */
  function mapFields(SalesforceMapping $sf_mapping, EntityInterface &$entity, $sf_object) {
    $foo = $sf_mapping->getPullFields($entity);
    $bar = $sf_mapping->get('field_mappings');

    // Field plugin crib sheet
    //$value = $sf_object[$field->get('salesforce_field')];
    //$drupal_field = $field->get('drupal_field_value');

    foreach ($sf_mapping->getPullFields($entity) as $field_map) {
      // $poop = $field_map->get('drupal_field_value');
      // $drupal_fields_array = explode(':', $field_map->get('drupal_field_value'));
      // $parent = $entity;
      $mapping_field_plugin_id = $field_map->get('drupal_field_type');
      $mapping_field_plugin = $this->pluginManager->create($mapping_field_plugin_id, $field_map);

      // $drupal_field_value = $field_map->get('drupal_field_value');
        
      try {
        $value = $mapping_field_plugin->getPullValue($entity);
      }
      catch (Exception $e) {
        watchdog_exception('sfpull', $e);
        continue;
      }

      // @TODO: make this work for reference fields. There must be a better way than a semi-colon delimited string to represent this.
      // It should look more like this in the future:
      // $drupal_field->getValue($entity);

      // Traverse through the field_value identifier to the child-most element. Practically this is in order to fine referenced entities. Right now we're ignoring that those exist and assuming that the field will have a value.
      // foreach ($drupal_fields_array as $drupal_field) {
      // }

      // $fieldmap_type = salesforce_mapping_get_fieldmap_types($field_map->get('drupal_field_type'));
      // $value = call_user_func($fieldmap_type['pull_value_callback'], $parent, $sf_object, $field_map);

      // Allow this value to be altered before assigning to the entity.
      drupal_alter('salesforce_pull_entity_value', $value, $field_map, $sf_object);
      // if (isset($value)) {
      //   // @TODO: might wrongly assumes an individual value wouldn't be an
      //   // array.
      //   if ($parent instanceof EntityListWrapper && !is_array($value)) {
      //     $parent->offsetSet(0, $value);
      //   }
      //   else {
      //     $parent->set($value);
      //   }
      }
    }
  }
}
