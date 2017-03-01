<?php

/**
 * @file
 * Contains Drupal\salesforce_pull\Plugin\QueueWorker\PullBase.php
 */

namespace Drupal\salesforce_pull\Plugin\QueueWorker;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Utility\Error;
use Drupal\salesforce\Exception;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce\SObject;
use Drupal\salesforce\SalesforceEvents;
use Drupal\salesforce_mapping\Entity\MappedObject;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\MappedObjectStorage;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\salesforce_mapping\PushParams;
use Drupal\salesforce_mapping\SalesforceMappingStorage;
use Drupal\salesforce_mapping\SalesforcePullEvent;
use Psr\Log\LogLevel;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides base functionality for the Salesforce Pull Queue Workers.
 */
abstract class PullBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

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
   * Storage handler for SF mappings.
   *
   * @var SalesforceMappingStorage
   */
  protected $mapping_storage;

  /**
   * Storage handler for Mapped Objects.
   *
   * @var MappedObjectStorage
   */
  protected $mapped_object_storage;

  /**
   * Logger service.
   *
   * @var LoggerInterface
   */
  protected $logger;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $event_dispatcher;

  /**
   * Creates a new PullBase object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   The entity type manager.
   * @param \Drupal\salesforce\Rest\RestClient $client
   *   Salesforce REST client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RestClient $client, LoggerChannelFactoryInterface $logger_factory, EventDispatcherInterface $event_dispatcher) {
    $this->etm = $entity_type_manager;
    $this->client = $client;
    $this->logger = $logger_factory->get('Salesforce Pull');
    $this->event_dispatcher = $event_dispatcher;
    $this->mapping_storage = $this->etm->getStorage('salesforce_mapping');
    $this->mapped_object_storage = $this->etm->getStorage('salesforce_mapped_object');
    $this->event_dispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('salesforce.client'),
      $container->get('logger.factory'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item) {
    $sf_object = $item->sobject;
    $mapping = $this->mapping_storage->load($item->mapping_id);
    if (!$mapping) {
      return;
    }

    // loadMappedObjects returns an array, but providing salesforce id and mapping guarantees at most one result.
    $mapped_object = $this->mapped_object_storage->loadByProperties([
      'salesforce_id' => (string)$sf_object->id(),
      'salesforce_mapping' => $mapping->id
    ]);
    // @TODO one-to-many: this is a blocker for OTM support:
    $mapped_object = current($mapped_object);
    if (!empty($mapped_object)) {
      return $this->updateEntity($mapping, $mapped_object, $sf_object);
    }
    else {
      return $this->createEntity($mapping, $sf_object);
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
        $this->logger->log(
          LogLevel::ERROR,
          'Drupal entity existed at one time for Salesforce object %sfobjectid, but does not currently exist. Error: %msg',
          [
            '%sfobjectid' => (string)$sf_object->id(),
            '%msg' => $e->getMessage(),
          ]
        );
        return;
      }

      // Flag this entity as having been processed. This does not persist,
      // but is used by salesforce_push to avoid duplicate processing.
      $entity->salesforce_pull = TRUE;

      $entity_updated = !empty($entity->changed->value)
        ? $entity->changed->value
        : $mapped_object->get('entity_updated');

      $pull_trigger_date =
        $sf_object->field($mapping->getPullTriggerDate());
      $sf_record_updated = strtotime($pull_trigger_date);

      $mapped_object
        ->setDrupalEntity($entity)
        ->setSalesforceRecord($sf_object);

      $this->event_dispatcher->dispatch(
        SalesforceEvents::PULL_PREPULL,
        $this->salesforcePullEvent($mapped_object, MappingConstants::SALESFORCE_MAPPING_SYNC_SF_UPDATE)
      );

      // @TODO allow some means for contrib to force pull regardless
      // of updated dates
      if ($sf_record_updated > $entity_updated) {
        // Set fields values on the Drupal entity.
        $mapped_object->pull();
        $this->logger->log(
          LogLevel::NOTICE,
          'Updated entity %label associated with Salesforce Object ID: %sfid',
          [
            '%label' => $entity->label(),
            '%sfid' => (string)$sf_object->id(),
          ]
        );
        return MappingConstants::SALESFORCE_MAPPING_SYNC_SF_UPDATE;
      }
    }
    catch (\Exception $e) {
      $this->logger->log(
        LogLevel::ERROR,
        'Failed to update entity %label from Salesforce object %sfobjectid. Error: %msg',
        [
          '%label' => (isset($entity)) ? $entity->label() : "Unknown",
          '%sfobjectid' => (string)$sf_object->id(),
          '%msg' => $e->getMessage(),
        ]
      );

      $this->logger->log(
        LogLevel::ERROR,
        '%type: @message in %function (line %line of %file).',
        Error::decodeException($e)
      );
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
      $entity = $this->etm
        ->getStorage($entity_type)
        ->create($values);

      // Create mapping object.
      $mapped_object = $this->mapped_object_storage->create([
        'entity_type_id' => $entity_type,
        'salesforce_mapping' => $mapping->id,
        'salesforce_id' => (string)$sf_object->id()
      ]);
      $mapped_object
        ->setDrupalEntity($entity)
        ->setSalesforceRecord($sf_object);

      $this->event_dispatcher->dispatch(
        SalesforceEvents::PULL_PREPULL,
        $this->salesforcePullEvent($mapped_object, MappingConstants::SALESFORCE_MAPPING_SYNC_SF_CREATE)
      );

      $mapped_object->pull();

      // Push upsert ID to SF object
      // @TODO make this optional / configurable
      $params = new PushParams($mapping, $entity);
      $this->client->objectUpdate(
        $mapping->getSalesforceObjectType(),
        $mapped_object->sfid(),
        $params->getParams()
      );
      $this->logger->log(
        LogLevel::NOTICE,
        'Created entity %id %label associated with Salesforce Object ID: %sfid',
        [
          '%id' => $entity->id(),
          '%label' => $entity->label(),
          '%sfid' => (string)$sf_object->id(),
        ]
      );
      return MappingConstants::SALESFORCE_MAPPING_SYNC_SF_CREATE;
    }
    catch (\Exception $e) {
      $this->logger->log(
        LogLevel::ERROR,
        '%msg Pull-create failed for Salesforce Object ID: %sfobjectid',
        [
          '%msg' => $e->getMessage(),
          '%sfobjectid' => (string)$sf_object->id(),
        ]
      );
      $this->logger->log(
        LogLevel::ERROR,
        '%type: @message in %function (line %line of %file).',
        Error::decodeException($e)
      );
    }
  }

  /**
   * Wrapper function for SalesforcePullEvent
   *
   * @param  MappedObjectInterface $mapped_object
   * @param  MappingConstants $mapping_constant
   */
  public function salesforcePullEvent($mapped_object, $mapping_constant) {
    return new SalesforcePullEvent($mapped_object, $mapping_constant);
  }
}
