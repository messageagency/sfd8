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
  public function processItem($sf_record) {
    try {
      $mapping = salesforce_mapping_load($sf_record['__salesforce_mapping_id']);
    }
    catch (Exception $e) {
      // If the mapping was deleted since this pull queue item was added, no
      // further processing can be done and we allow this item to be deleted.
      return;
    }

    try {
      // salesforce_mapped_object_load_multiple() returns an array, but providing salesforce id and mapping guarantees at most one result.
      $mapped_object = salesforce_mapped_object_load_multiple(['salesforce_id' => $sf_record['Id'], 'salesforce_mapping' => $mapping->id()]);
      $mapped_object = current($mapped_object);
      $this->updateEntity($sf_mapping, $mapped_object, $sf_record);
    }
    catch (Exception $e) {
      $this->createEntity($sf_mapping, $sf_record);
    }

  }

  /**
   * Update an existing Drupal entity
   *
   * @param object $sf_mapping
   *   Object of field maps.
   * @param object $mapped_object
   *   SF Mmapped object.
   * @param array $sf_record
   *   Current Salesforce record array.
   */
  private function updateEntity(SalesforceMapping $sf_mapping, MappedObject $mapped_object, array $sf_record) {
    if (!$sf_mapping->checkTriggers([SALESFORCE_MAPPING_SYNC_SF_UPDATE])) {
      return;
    }

    try {
      $entity = \Drupal::entityTypeManager()
        ->getStorage($mapped_object->get('entity_type_id')->value)
        ->load($mapped_object->get('entity_id')->value);

      // Flag this entity as having been processed. This does not persist,
      // but is used by salesforce_push to avoid duplicate processing.
      $entity->salesforce_pull = TRUE;

      $entity_updated = !empty($entity->changed->value)
        ? $entity->changed->value
        : $mapped_object->get('entity_updated');

      $sf_record_updated = strtotime($sf_record[$sf_mapping->get('pull_trigger_date')]);

      \Drupal::moduleHandler()->alter('salesforce_pull_pre_pull', $sf_record, $mapped_object, $entity);

      // "__salesforce_force_pull" allows contrib to force pull regardless
      // of updated dates. @TODO make this more better.
      if ($sf_record['__salesforce_force_pull']
      || $sf_record_updated > $entity_updated) {
        // Set fields values on the Drupal entity.
        $mapped_object
          ->setDrupalEntity($entity)
          ->setSalesforceRecord($sf_record)
          ->pull();
        \Drupal::logger('Salesforce Pull')->notice(
          'Updated entity %label associated with Salesforce Object ID: %sfid',
          [
            '%label' => $entity->label(),
            '%sfid' => $sf_record['Id'],
          ]
        );

      }
    }
    catch (Exception $e) {
      $message = t('Failed to update entity %label from Salesforce object %sfobjectid. Error: %msg',
        [
          '%label' => $entity->label(),
          '%sfobjectid' => $sf_record['Id'],
          '%msg' => $e->getMessage(),
        ]
      );
      \Drupal::logger('Salesforce Pull')->error($message);
    }
  }

  /**
   * Create a Drupal entity and mapped object
   *
   * @param object $sf_mapping
   *   Object of field maps.
   * @param array $sf_record
   *   Current Salesforce record array.
   */
  private function createEntity(SalesforceMapping $sf_mapping, array $sf_record) {
    if (!$sf_mapping->checkTriggers([SALESFORCE_MAPPING_SYNC_SF_CREATE])) {
      return;
    }

    try {
      // Create entity from mapping object and field maps.
      $entity_type = $sf_mapping->get('drupal_entity_type');
      $entity_info = \Drupal::entityTypeManager()->getDefinition($entity_type);

      // Define values to pass to entity_create().
      $entity_keys = $entity_info->getKeys();
      $values = [];
      if (isset($entity_keys['bundle']) 
      && !empty($entity_keys['bundle'])) {
        $values[$entity_keys['bundle']] = $sf_mapping->get('drupal_bundle');
      }

      // See note above about flag.
      $values['salesforce_pull'] = TRUE;

      // Create entity.
      $entity = \Drupal::entityTypeManager()
        ->getStorage($entity_type)
        ->create($values);

      // Flag this entity as having been processed. This does not persist,
      // but is used by salesforce_push to avoid duplicate processing.
      $entity->salesforce_pull = TRUE;

      // Create mapping object.
      $mapped_object = \Drupal::entityTypeManager()
        ->getStorage('salesforce_mapped_object')
        ->create([
          'entity_type_id' => $entity_type,
          'salesforce_mapping' => $sf_mapping->id(),
          'salesforce_id' => $sf_record['Id'],
        ]);

        \Drupal::moduleHandler()->alter('salesforce_pull_pre_pull', $sf_record, $mapped_object, $entity);

      $mapped_object
        ->setDrupalEntity($entity)
        ->setSalesforceRecord($sf_record)
        ->pull();

      \Drupal::logger('Salesforce Pull')->notice(
        'Created entity %id %label associated with Salesforce Object ID: %sfid',
        [
          '%id' => $entity->id(),
          '%label' => $entity->label(),
          '%sfid' => $sf_record['Id'],
        ]
      );
    }
    catch (Exception $e) {
      $message = $e->getMessage() . ' ' . t('Pull-create failed for Salesforce Object ID: %sfobjectid',
        [
          '%sfobjectid' => $sf_record['Id'],
        ]
      );
      \Drupal::logger('Salesforce Pull')->error($message);
    }
  }

}
