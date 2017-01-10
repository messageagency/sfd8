<?php

/**
 * @file
 * Contains Drupal\salesforce_pull\Plugin\QueueWorker\PullBase.php
 */

namespace Drupal\salesforce_pull\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\salesforce\Exception;
use Drupal\salesforce\EntityNotFoundException;
use Drupal\salesforce\SObject;
use Drupal\salesforce\LoggingTrait;
use Drupal\salesforce\LoggingLevels;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce_mapping\Entity\MappedObject;
use Drupal\salesforce_mapping\PushParams;

/**
 * Provides base functionality for the Salesforce Pull Queue Workers.
 */
abstract class PullBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  use LoggingTrait;

  /**
   * The entity type manager
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $etm;

  /**
   * The SF REST client.
   *
   * @var Drupal\salesforce\Rest\RestClient
   */
  protected $client;

  /**
   * The module handler.
   *
   * @var Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $mh;

  /**
   * Creates a new PullBase object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RestClient $client, ModuleHandlerInterface $module_handler) {
    $this->etm = $entity_type_manager;
    $this->client = $client;
    $this->mh = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('salesforce.client'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    $sf_object = $item->sobject;
    try {
      $mapping = $this->loadMapping($item->mapping_id);
    }
    catch (\Exception $e) {
      // If the mapping was deleted since this pull queue item was added, no
      // further processing can be done and we allow this item to be deleted.
      return;
    }

    try {
      // loadMappingObjects returns an array, but providing salesforce id and mapping guarantees at most one result.
      $mapped_object = $this->loadMappingObjects([
        'salesforce_id' => (string)$sf_object->id(),
        'salesforce_mapping' => $mapping->id()
      ]);
      $mapped_object = current($mapped_object);
      $this->updateEntity($mapping, $mapped_object, $sf_object);
    }
    catch (\Exception $e) {
      $this->createEntity($mapping, $sf_object);
    }

  }

  /**
   * Update an existing Drupal entity
   *
   * @param object $mapping
   *   Object of field maps.
   * @param object $mapped_object
   *   SF Mmapped object.
   * @param SObject $sf_object
   *   Current Salesforce record array.
   */
  private function updateEntity(SalesforceMapping $mapping, MappedObject $mapped_object, SObject $sf_object) {
    if (!$mapping->checkTriggers([SALESFORCE_MAPPING_SYNC_SF_UPDATE])) {
      return;
    }

    try {
      $entity = $this->etm->getStorage($mapped_object->entity_type_id->value)
        ->load($mapped_object->entity_id->value);
      if (!$entity) {
        throw new EntityNotFoundException($mapped_object->entity_id->value, $mapped_object->entity_type_id->value);
      }

      // Flag this entity as having been processed. This does not persist,
      // but is used by salesforce_push to avoid duplicate processing.
      $entity->salesforce_pull = TRUE;

      $entity_updated = !empty($entity->changed->value)
        ? $entity->changed->value
        : $mapped_object->get('entity_updated');

      $pull_trigger_date =
        $sf_object->field($mapping->get('pull_trigger_date'));
      $sf_record_updated = strtotime($pull_trigger_date);

      $this->mh->alter('salesforce_pull_pre_pull', $sf_object, $mapped_object, $entity);

      // @TODO allow some means for contrib to force pull regardless
      // of updated dates
      if ($sf_record_updated > $entity_updated) {
        // Set fields values on the Drupal entity.
        $mapped_object
          ->setDrupalEntity($entity)
          ->setSalesforceRecord($sf_object)
          ->pull();
        $this->log('Salesforce Pull',
          LoggingLevels::NOTICE,
          'Updated entity %label associated with Salesforce Object ID: %sfid',
          [
            '%label' => $entity->label(),
            '%sfid' => (string)$sf_object->id(),
          ]
        );
      }
    }
    catch (\Exception $e) {
      if ($e instanceof EntityNotFoundException) {
        $message = t('Drupal entity existed at one time for Salesforce object %sfobjectid, but does not currently exist. Error: %msg',
          [
            '%sfobjectid' => (string)$sf_object->id(),
            '%msg' => $e->getMessage(),
          ]
        );
      }
      else {
        $message = t('Failed to update entity %label from Salesforce object %sfobjectid. Error: %msg',
          [
            '%label' => $entity->label(),
            '%sfobjectid' => (string)$sf_object->id(),
            '%msg' => $e->getMessage(),
          ]
        );
      }
      $this->log('Salesforce Pull', LoggingLevels::ERROR, $message);
      $this->watchdogException($e);
    }
  }

  /**
   * Create a Drupal entity and mapped object
   *
   * @param object $mapping
   *   Object of field maps.
   * @param SObject $sf_object
   *   Current Salesforce record array.
   */
  private function createEntity(SalesforceMapping $mapping, SObject $sf_object) {
    if (!$mapping->checkTriggers([SALESFORCE_MAPPING_SYNC_SF_CREATE])) {
      return;
    }

    try {
      // Create entity from mapping object and field maps.
      $entity_type = $mapping->get('drupal_entity_type');
      $entity_info = $this->etm->getDefinition($entity_type);

      // Define values to pass to entity_create().
      $entity_keys = $entity_info->getKeys();
      $values = [];
      if (isset($entity_keys['bundle'])
      && !empty($entity_keys['bundle'])) {
        $values[$entity_keys['bundle']] = $mapping->get('drupal_bundle');
      }

      // See note above about flag.
      $values['salesforce_pull'] = TRUE;

      // Create entity.
      $entity = $this->etm->getStorage($entity_type)->create($values);

      // Flag this entity as having been processed. This does not persist,
      // but is used by salesforce_push to avoid duplicate processing.
      $entity->salesforce_pull = TRUE;

      // Create mapping object.
      $mapped_object = $this->etm->getStorage('salesforce_mapped_object')
        ->create([
          'entity_type_id' => $entity_type,
          'salesforce_mapping' => $mapping->id(),
          'salesforce_id' => (string)$sf_object->id(),
        ]);

      $this->mh->alter('salesforce_pull_pre_pull', $sf_object, $mapped_object, $entity);

      $mapped_object
        ->setDrupalEntity($entity)
        ->setSalesforceRecord($sf_object)
        ->pull();

      // Push upsert ID to SF object
      $params = new PushParams($mapping, $entity);
      $this->client->objectUpdate(
        $mapping->getSalesforceObjectType(),
        $mapped_object->sfid(),
        $params->getParams()
      );

      $this->log(
        'Salesforce Pull',
        LoggingLevels::NOTICE,
        'Created entity %id %label associated with Salesforce Object ID: %sfid',
        [
          '%id' => $entity->id(),
          '%label' => $entity->label(),
          '%sfid' => (string)$sf_object->id(),
        ]
      );
    }
    catch (\Exception $e) {
      $message = $e->getMessage() . ' ' . t('Pull-create failed for Salesforce Object ID: %sfobjectid',
        [
          '%sfobjectid' => (string)$sf_object->id(),
        ]
      );
      $this->log('Salesforce Pull', LoggingLevels::ERROR, $message);
      $this->watchdogException($e);
    }
  }

  /**
   * Wrapper for salesforce_mapping_load();
   */
  protected function loadMapping($id) {
    return salesforce_mapping_load($id);
  }

  /**
   * Wrapper for salesforce_mapped_object_load_multiple();
   */
  protected function loadMappingObjects(array $properties) {
    return salesforce_mapped_object_load_multiple($properties);
  }
}
