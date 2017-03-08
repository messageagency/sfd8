<?php

namespace Drupal\salesforce_push\Plugin\SalesforcePushQueueProcessor;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\salesforce\EntityNotFoundException;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\SalesforceEvents;
use Drupal\salesforce_mapping\Entity\MappedObject;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\salesforce_mapping\SalesforcePushOpEvent;
use Drupal\salesforce_push\PushQueue;
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
  protected $queue;
  protected $client;

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
  protected $mapped_object_storage;
  protected $event_dispatcher;
  protected $etm;

  public function __construct(array $configuration, $plugin_id, array $plugin_definition, PushQueue $queue, RestClientInterface $client,  EntityTypeManager $etm, ContainerAwareEventDispatcher $event_dispatcher) {
    $this->queue = $queue;
    $this->client = $client;
    $this->etm = $etm;
    $this->mapping_storage = $etm->getStorage('salesforce_mapping');
    $this->mapped_object_storage = $etm->getStorage('salesforce_mapped_object');
    $this->event_dispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('queue.salesforce_push'),
      $container->get('salesforce.client'),
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher')
    );
  }

  public function process(array $items) {
    if (!$this->client->isAuthorized()) {
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

  protected function processItem(\stdClass $item) {
    $mapped_object = $this
      ->mapped_object_storage
      ->load($item->mapped_object_id);

    // Allow exceptions to bubble up for PushQueue to sort things out.
    $mapping = $this->mapping_storage->load($item->name);

    if (!$mapped_object) {
      if ($item->op == MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE) {
        // If mapped object doesn't exist or fails to load for this delete, this item can be considered successfully processed.
        return;
      }
      $mapped_object = new MappedObject([
        'entity_id' => $item->entity_id,
        'entity_type_id' => $mapping->drupal_entity_type,
        'salesforce_mapping' => $mapping->id(),
      ]);
    }

    // @TODO: the following is nearly identical to the end of salesforce_push_entity_crud(). Can we DRY it? Do we care?
    try {
      $this->event_dispatcher->dispatch(
        SalesforceEvents::PUSH_MAPPING_OBJECT,
        new SalesforcePushOpEvent($mapped_object, $item->op)
      );

      // If this is a delete, destroy the SF object and we're done.
      if ($item->op == MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE) {
        $mapped_object->pushDelete();
      }
      else {
        $entity = $this->etm
          ->getStorage($mapping->drupal_entity_type)
          ->load($item->entity_id);
        if (!$entity) {
          // Bubble this up also
          throw new EntityNotFoundException($item->entity_id, $mapping->drupal_entity_type);
        }

        // Push to SF. This also saves the mapped object.
        $mapped_object
          ->setDrupalEntity($entity)
          ->push();
      }
    }
    catch (\Exception $e) {
      $this->event_dispatcher->dispatch(
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

}
