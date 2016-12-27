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
use Drupal\salesforce_mapping\Entity\MappedObject;
use Drupal\salesforce\Exception;

/**
 * Provides base functionality for the Salesforce Pull Queue Workers.
 */
abstract class PullBase extends QueueWorkerBase {

  /**
   * Creates a new PullBase object.
   */
  public function __construct() {}

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

    try {
      $sf_mappings = salesforce_mapping_load_multiple($mapping_conditions);
    }
    catch (Exception $e) {
      return;
    }

    foreach ($sf_mappings as $sf_mapping) {
      try {
        // @TODO: Does salesforce_mapped_object_load_by_sfid need to return
        // multiple objects?
        $mapped_objects = salesforce_mapped_object_load_by_sfid($sf_object['Id']);
        foreach ($mapped_objects as $mapped_object) {
          $this->updateEntity($sf_mapping, $mapped_object, $sf_object);
        }
      }
      catch (Exception $e) {
        $this->createEntity($sf_mapping, $sf_object);
      }
    }
  }

  /**
   * Update an existing Drupal entity
   *
   * @param object $sf_mapping
   *   Object of field maps.
   * @param object $mapped_object
   *   SF Mmapped object.
   * @param array $sf_object
   *   Current Salesforce record array.
   */
  private function updateEntity(SalesforceMapping $sf_mapping, MappedObject $mapped_object, array $sf_object) {
    if ($sf_mapping->checkTriggers([SALESFORCE_MAPPING_SYNC_SF_UPDATE])) {
      try {
        $entity = \Drupal::entityTypeManager()
          ->getStorage($mapped_object->get('entity_type_id')->value)
          ->load($mapped_object->get('entity_id')->value);

        // Flag this entity as having been processed. This does not persist,
        // but is used by salesforce_push to avoid duplicate processing.
        $entity->salesforce_pull = TRUE;
        $entity_updated = !empty($entity->get('changed')->value)
          ? $entity->get('changed')->value
          : $mapped_object->get('entity_updated');
        $sf_object_updated = strtotime($sf_object[$sf_mapping->get('pull_trigger_date')]);
        if ($sf_object_updated > $entity_updated) {
          // Set fields values on the Drupal entity.
          $mapped_object->pull($sf_object, $entity);
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
            '%label' => $entity->label(),
            '%sfobjectid' => $sf_object['Id'],
            '%msg' => $e->getMessage(),
          ]
        );
        \Drupal::logger('Salesforce Pull')->error($message);
        salesforce_set_message($message, 'error', FALSE);
      }
    }
  }

  /**
   * Create a Drupal entity and mapped object
   *
   * @param object $sf_mapping
   *   Object of field maps.
   * @param array $sf_object
   *   Current Salesforce record array.
   */
  private function createEntity(SalesforceMapping $sf_mapping, array $sf_object) {
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

        $this->mapFields($sf_mapping, $entity, $sf_object);
        // Entity::save() throws an exception on failure, so no need for
        // addtional exception
        $entity->save();

        // Create mapping object.
        $mapped_object = \Drupal::entityTypeManager()
          ->getStorage('salesforce_mapped_object')
          ->create([
            'entity_type_id' => $sf_mapping->get('drupal_entity_type'),
            'entity_id' => $entity->id(),
            'salesforce_mapping' => $sf_mapping->id(),
            'salesforce_id' => $sf_object['Id'],
          ])
          ->save();

        \Drupal::logger('Salesforce Pull')->notice(
          'Created entity %label associated with Salesforce Object ID: %sfid',
          [
            '%label' => $entity->label(),
            '%sfid' => $sf_object['Id'],
          ]
        );
      }
      catch (Exception $e) {
        $message = $e->getMessage() . ' ' . t('Processing failed for entity %label associated with Salesforce Object ID: %sfobjectid',
          [
            '%label' => $entity->label(),
            '%sfobjectid' => $sf_object['Id'],
          ]
        );
        \Drupal::logger('Salesforce Pull')->error($message);
        salesforce_set_message('There were failures processing data from SalesForce. Please check the error logs.', 'error', FALSE);
      }
    }
  }

  /**
   * Map field values.
   *
   * @param object $sf_mapping
   *   Object of field maps.
   * @param object $entity
   *   Entity object.
   * @param array $sf_object
   *   Current Salesforce record array.
   * @TODO this should move into SalesforceMapping.php
   */
  function mapFields(SalesforceMapping $sf_mapping, EntityInterface &$entity, $sf_object) {
    foreach ($sf_mapping->getPullFields() as $field) {
      try {
        $entity->set(
          $field->get('drupal_field_value'),
          $sf_object[$field->get('salesforce_field')]
        );
      }
      catch (Exception $e) {
        \Drupal::logger('Salesforce Pull')->error($e->getMessage());
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
      \Drupal::moduleHandler()->alter('salesforce_pull_entity_value', $sf_object[$field->get('salesforce_field')], $entity, $field);
      // if (isset($value)) {
      //   // @TODO: might wrongly assumes an individual value wouldn't be an
      //   // array.
      //   if ($parent instanceof EntityListWrapper && !is_array($value)) {
      //     $parent->offsetSet(0, $value);
      //   }
      //   else {
      //     $parent->set($value);
      //   }
      //}
    }
  }
}
