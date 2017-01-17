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
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_mapping\PushParams;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\salesforce_mapping\SalesforceMappingStorage;
use Drupal\salesforce_mapping\MappedObjectStorage;


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
   * Internal flow tracker for testing.
   *
   * @var string
   */
  protected $done;

  /**
   * Storage handler for SF mappings
   *
   * @var SalesforceMappingStorage
   */
  protected $mapping_storage;

  /**
   * Storage handler for Mapped Objects
   *
   * @var MappedObjectStorage
   */
  protected $mapped_object_storage

  /**
   * Creates a new PullBase object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RestClient $client, ModuleHandlerInterface $module_handler, SalesforceMappingStorage $mapping_storage, MappedObjectStorage $mapped_object_storage) {
    $this->etm = $entity_type_manager;
    $this->client = $client;
    $this->mh = $module_handler;
    $this->mapping_storage = $mapping_storage;
    $this->mapped_object_storage = $mapped_object_storage;
    $this->done = '';
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('salesforce.client'),
      $container->get('module_handler'),
      $container->get('salesforce.salesforce_mapping_storage'),
      $container->get('salesforce.mapped_object_storage')
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
      // loadMappedObjects returns an array, but providing salesforce id and mapping guarantees at most one result.
      $mapped_object = $this->loadMappedObjects([
        'salesforce_id' => (string)$sf_object->id(),
        'salesforce_mapping' => $mapping->id
      ]);
      // @TODO one-to-many: this is a blocker for OTM support:
      $mapped_object = current($mapped_object);
      $this->updateEntity($mapping, $mapped_object, $sf_object);
      $this->done = 'update';
    }
    catch (\Exception $e) {
      $this->createEntity($mapping, $sf_object);
      $this->done = 'create';
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
  protected function updateEntity(SalesforceMappingInterface $mapping, MappedObjectInterface $mapped_object, SObject $sf_object) {
    if (!$mapping->checkTriggers([MappingConstants::SALESFORCE_MAPPING_SYNC_SF_UPDATE])) {
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
        $this->log('Salesforce Pull',
          LoggingLevels::ERROR,
          'Drupal entity existed at one time for Salesforce object %sfobjectid, but does not currently exist. Error: %msg',
          [
            '%sfobjectid' => (string)$sf_object->id(),
            '%msg' => $e->getMessage(),
          ]
        );
      }
      else {
        $this->log('Salesforce Pull',
          LoggingLevels::ERROR,
          'Failed to update entity %label from Salesforce object %sfobjectid. Error: %msg',
          [
            '%label' => $entity->label(),
            '%sfobjectid' => (string)$sf_object->id(),
            '%msg' => $e->getMessage(),
          ]
        );
      }
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
  protected function createEntity(SalesforceMappingInterface $mapping, SObject $sf_object) {
    if (!$mapping->checkTriggers([MappingConstants::SALESFORCE_MAPPING_SYNC_SF_CREATE])) {
      return;
    }

    try {
      // Define values to pass to entity_create().
      $entity_type = $mapping->getDrupalEntityType();
      $entity_keys = $this->etm->getDefinition($entity_type)->getKeys();
      $values = [];
      if (isset($entity_keys['bundle'])
      && !empty($entity_keys['bundle'])) {
        $values[$entity_keys['bundle']] = $mapping->getDrupalBundle();
      }

      // See note above about flag.
      $values['salesforce_pull'] = TRUE;

      // Create entity.
      $entity = $this->etm->getStorage($entity_type)->create($values);

      // Flag this entity as having been processed. This does not persist,
      // but is used by salesforce_push to avoid duplicate processing.
      $entity->salesforce_pull = TRUE;

      // Create mapping object.
      $mapped_object = new MappedObject([
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
      $this->log('Salesforce Pull',
        LoggingLevels::ERROR,
        '%msg Pull-create failed for Salesforce Object ID: %sfobjectid',
        [
          '%msg' => $e->getMessage(),
          '%sfobjectid' => (string)$sf_object->id(),
        ]
      );
      $this->watchdogException($e);
    }
  }

  /**
   * Return internal process tracking property
   */
  public function getDone() {
    return $this->done;
  }

  /**
   * Wrapper for salesforce_mapping load();
   */
  protected function loadMapping($id) {
    return $this->mapping_storage->load($id);
  }

  /**
   * Wrapper for salesforce_mapped_object_load_multiple();
   */
  protected function loadMappedObjects(array $properties) {
    return $this->mapped_object_storage->loadByProperties($properties);
  }
}
