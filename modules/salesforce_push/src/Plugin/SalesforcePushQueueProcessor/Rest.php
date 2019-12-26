<?php

namespace Drupal\salesforce_push\Plugin\SalesforcePushQueueProcessor;

use Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\salesforce\EntityNotFoundException;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce_mapping\Entity\MappedObject;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\Event\SalesforcePushOpEvent;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\salesforce_push\PushQueueInterface;
use Drupal\salesforce_push\PushQueueProcessorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Rest queue processor plugin.
 *
 * @Plugin(
 *   id = "rest",
 *   label = @Translation("REST Push Queue Processor")
 * )
 */
class Rest extends PluginBase implements PushQueueProcessorInterface {

  /**
   * Push queue service.
   *
   * @var \Drupal\salesforce_push\PushQueueInterface
   */
  protected $queue;

  /**
   * Storage handler for SF mappings.
   *
   * @var \Drupal\salesforce_mapping\SalesforceMappingStorage
   */
  protected $mappingStorage;

  /**
   * Storage handler for Mapped Objects.
   *
   * @var \Drupal\salesforce_mapping\MappedObjectStorage
   */
  protected $mappedObjectStorage;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * ETM service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $etm;

  /**
   * Auth manager.
   *
   * @var \Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface
   */
  protected $authMan;

  /**
   * Rest constructor.
   *
   * @param array $configuration
   *   Plugin config.
   * @param string $plugin_id
   *   Plugin id.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\salesforce_push\PushQueueInterface $queue
   *   Push queue service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   ETM service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   Event dispatcher service.
   * @param \Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface $authMan
   *   Auth manager.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, PushQueueInterface $queue, EntityTypeManagerInterface $etm, EventDispatcherInterface $eventDispatcher, SalesforceAuthProviderPluginManagerInterface $authMan) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->queue = $queue;
    $this->etm = $etm;
    $this->mappingStorage = $etm->getStorage('salesforce_mapping');
    $this->mappedObjectStorage = $etm->getStorage('salesforce_mapped_object');
    $this->eventDispatcher = $eventDispatcher;
    $this->authMan = $authMan;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('queue.salesforce_push'),
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('plugin.manager.salesforce.auth_providers')
    );
  }

  /**
   * Process push queue items.
   */
  public function process(array $items) {
    if (!$this->authMan->getToken()) {
      throw new SuspendQueueException('Salesforce client not authorized.');
    }
    foreach ($items as $item) {
      try {
        $this->processItem($item);
        $this->queue->deleteItem($item);
      }
      catch (\Exception $e) {
        $this->queue->failItem($e, $item);
      }
    }
  }

  /**
   * Push queue item process callback.
   *
   * @param object $item
   *   The push queue item.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function processItem(\stdClass $item) {
    // Allow exceptions to bubble up for PushQueue to sort things out.
    $mapping = $this->mappingStorage->load($item->name);
    $mapped_object = $this->getMappedObject($item, $mapping);

    if ($mapped_object->isNew()
    && $item->op == MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE) {
      // If mapped object doesn't exist or fails to load for this delete, this
      // item can be considered successfully processed.
      return;
    }

    // @TODO: the following is nearly identical to the end of salesforce_push_entity_crud(). Can we DRY it? Do we care?
    try {
      $this->eventDispatcher->dispatch(
        SalesforceEvents::PUSH_MAPPING_OBJECT,
        new SalesforcePushOpEvent($mapped_object, $item->op)
      );

      // If this is a delete, destroy the SF object and we're done.
      if ($item->op == MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE) {
        $mapped_object->pushDelete();
        // This has to be cleaned up here because we need the object to process
        // Async.
        $mapped_object->delete();
      }
      else {
        $entity = $this->etm
          ->getStorage($mapping->drupal_entity_type)
          ->load($item->entity_id);
        if ($entity === NULL) {
          // Bubble this up also.
          throw new EntityNotFoundException($item->entity_id, $mapping->drupal_entity_type);
        }

        // Push to SF. This also saves the mapped object.
        $mapped_object
          ->setDrupalEntity($entity)
          ->push();
      }
    }
    catch (\Exception $e) {
      $this->eventDispatcher->dispatch(
        SalesforceEvents::PUSH_FAIL,
        new SalesforcePushOpEvent($mapped_object, $item->op)
      );

      // Log errors and throw exception to cause this item to be re-queued.
      if (!$mapped_object->isNew()) {
        // Only update existing mapped objects.
        $mapped_object
          ->set('last_sync_action', $item->op)
          ->set('last_sync_status', FALSE)
          ->set('revision_log_message', $e->getMessage())
          ->save();
      }
      throw $e;
    }
  }

  /**
   * Return the mapped object given a queue item and mapping.
   *
   * @param object $item
   *   Push queue item.
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   The mapping.
   *
   * @return \Drupal\salesforce_mapping\Entity\MappedObject
   *   The mapped object.
   */
  protected function getMappedObject(\stdClass $item, SalesforceMappingInterface $mapping) {
    $mapped_object = FALSE;
    // Prefer mapped object id if we have one.
    if ($item->mapped_object_id) {
      $mapped_object = $this
        ->mappedObjectStorage
        ->load($item->mapped_object_id);
    }
    if ($mapped_object) {
      return $mapped_object;
    }

    // Fall back to entity+mapping, which is a unique key.
    if ($item->entity_id) {
      $mapped_object = $this
        ->mappedObjectStorage
        ->loadByProperties([
          'drupal_entity__target_type' => $mapping->drupal_entity_type,
          'drupal_entity__target_id' => $item->entity_id,
          'salesforce_mapping' => $mapping->id(),
        ]);
    }
    if ($mapped_object) {
      if (is_array($mapped_object)) {
        $mapped_object = current($mapped_object);
      }
      return $mapped_object;
    }

    return $this->createMappedObject($item, $mapping);
  }

  /**
   * Helper method to generate a new MappedObject during push procesing.
   *
   * @param object $item
   *   Push queue item.
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   The mapping.
   *
   * @return \Drupal\salesforce_mapping\Entity\MappedObjectInterface
   *   The new mapped object.
   */
  protected function createMappedObject(\stdClass $item, SalesforceMappingInterface $mapping) {
    return new MappedObject([
      'drupal_entity' => [
        'target_id' => $item->entity_id,
        'target_type' => $mapping->drupal_entity_type,
      ],
      'salesforce_mapping' => $mapping->id(),
    ]);
  }

}
