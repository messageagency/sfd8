<?php

namespace Drupal\salesforce_push;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Query\Merge;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\DatabaseQueue;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\State\StateInterface;
use Drupal\salesforce\EntityNotFoundException;
use Drupal\salesforce\Event\SalesforceErrorEvent;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Event\SalesforceNoticeEvent;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Salesforce push queue.
 *
 * @ingroup queue
 */
class PushQueue extends DatabaseQueue implements PushQueueInterface {

  /**
   * The database table name.
   */
  const TABLE_NAME = 'salesforce_push_queue';

  /**
   * Default max number of items to process in a single cron run.
   */
  const DEFAULT_GLOBAL_LIMIT = 10000;

  /**
   * Plugin id of default queue processor.
   */
  const DEFAULT_QUEUE_PROCESSOR = 'rest';

  /**
   * Default number of fails to consider a record permanently failed.
   */
  const DEFAULT_MAX_FAILS = 10;

  /**
   * Default lease time for push queue items.
   */
  const DEFAULT_LEASE_TIME = 300;

  /**
   * Global limit from config.
   *
   * @var int
   */
  protected $globalLimit;

  /**
   * Max fails from config.
   *
   * @var int
   */
  protected $maxFails;

  /**
   * Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * State service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Push queue plugin manager.
   *
   * @var \Drupal\salesforce_push\PushQueueProcessorPluginManager
   */
  protected $queueManager;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Whether garbage has been collected.
   *
   * @var bool
   */
  protected $garbageCollected;

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
   * Time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Config service.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * ETM service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $etm;

  /**
   * PushQueue constructor.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Database service.
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   * @param \Drupal\salesforce_push\PushQueueProcessorPluginManager $queue_manager
   *   Queue plugin manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   ETM service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   Config service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(Connection $connection, StateInterface $state, PushQueueProcessorPluginManager $queue_manager, EntityTypeManagerInterface $etm, EventDispatcherInterface $event_dispatcher, TimeInterface $time, ConfigFactoryInterface $config) {
    $this->connection = $connection;
    $this->state = $state;
    $this->queueManager = $queue_manager;
    $this->etm = $etm;
    $this->mappingStorage = $etm->getStorage('salesforce_mapping');
    $this->mappedObjectStorage = $etm->getStorage('salesforce_mapped_object');
    $this->eventDispatcher = $event_dispatcher;
    $this->time = $time;

    $this->config = $config->get('salesforce.settings');
    $this->globalLimit = $this->config->get('global_push_limit') ?: static::DEFAULT_GLOBAL_LIMIT;
    if (empty($this->globalLimit)) {
      $this->globalLimit = static::DEFAULT_GLOBAL_LIMIT;
    }
    $this->maxFails = $state->get('salesforce.push_queue_max_fails', static::DEFAULT_MAX_FAILS);
    if (empty($this->maxFails)) {
      $this->maxFails = static::DEFAULT_MAX_FAILS;
    }
    $this->garbageCollected = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('state'),
      $container->get('plugin.manager.salesforce_push_queue_processor'),
      $container->get('entity_type.manager'),
      $container->get('event_dispatcher'),
      $container->get('datetime.time'),
      $container->get('config.factory')
    );
  }

  /**
   * Set queue name property.
   *
   * Parent class DatabaseQueue relies heavily on $this->name, so it's best to
   * just set the value appropriately.
   *
   * @param string $name
   *   Queue name. For us it's the Mapping id.
   *
   * @return $this
   */
  public function setName($name) {
    $this->name = $name;
    return $this;
  }

  /**
   * Adds a queue item and store it directly to the queue.
   *
   * @param array $data
   *   Data array with the following key-value pairs:
   *   * 'name': the name of the salesforce mapping for this entity
   *   * 'entity_id': the entity id being mapped / pushed
   *   * 'op': the operation which triggered this push.
   *
   * @return int
   *   On success, \Drupal\Core\Database\Query\Merge::STATUS_INSERT or
   *   Drupal\Core\Database\Query\Merge::STATUS_UPDATE whether item was inserted
   *   or updated.
   *
   * @throws \Exception
   *   If the required indexes are not provided.
   *
   * @TODO convert $data to a proper class and make sure that's what we get for this argument.
   */
  protected function doCreateItem($data) { // @codingStandardsIgnoreLine
    if (empty($data['name'])
    || empty($data['entity_id'])
    || empty($data['op'])) {
      throw new \Exception('Salesforce push queue data values are required for "name", "entity_id" and "op"');
    }
    $this->name = $data['name'];
    $time = $this->time->getRequestTime();
    $fields = [
      'name' => $this->name,
      'entity_id' => $data['entity_id'],
      'op' => $data['op'],
      'updated' => $time,
      'failures' => empty($data['failures'])
      ? 0
      : $data['failures'],
      'mapped_object_id' => empty($data['mapped_object_id'])
      ? 0
      : $data['mapped_object_id'],
    ];

    $query = $this->connection->merge(static::TABLE_NAME)
      ->key(['name' => $this->name, 'entity_id' => $data['entity_id']])
      ->fields($fields);

    // Return Merge::STATUS_INSERT or Merge::STATUS_UPDATE.
    $ret = $query->execute();

    // Drupal still doesn't support now() https://www.drupal.org/node/215821
    // 11 years.
    if ($ret == Merge::STATUS_INSERT) {
      $this->connection->merge(static::TABLE_NAME)
        ->key(['name' => $this->name, 'entity_id' => $data['entity_id']])
        ->fields(['created' => $time])
        ->execute();
    }
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function claimItems($n, $fail_limit = self::DEFAULT_MAX_FAILS, $lease_time = self::DEFAULT_LEASE_TIME) {
    while (TRUE) {
      try {
        if ($n <= 0) {
          // If $n is zero, process as many items as possible.
          $n = $this->globalLimit;
        }
        // @TODO: convert items to content entities.
        // @see \Drupal::entityQuery()
        $items = $this->connection->queryRange('SELECT * FROM {' . static::TABLE_NAME . '} q WHERE expire = 0 AND name = :name AND failures < :fail_limit ORDER BY created, item_id ASC', 0, $n, [':name' => $this->name, ':fail_limit' => $fail_limit])->fetchAllAssoc('item_id');
      }
      catch (\Exception $e) {
        $this->catchException($e);
        // If the table does not exist there are no items currently available to
        // claim.
        return [];
      }
      if ($items) {
        // Try to update the item. Only one thread can succeed in UPDATEing the
        // same row. We cannot rely on REQUEST_TIME because items might be
        // claimed by a single consumer which runs longer than 1 second. If we
        // continue to use REQUEST_TIME instead of the current time(), we steal
        // time from the lease, and will tend to reset items before the lease
        // should really expire.
        $update = $this->connection->update(static::TABLE_NAME)
          ->fields([
            'expire' => $this->time->getRequestTime() + $lease_time,
          ])
          ->condition('item_id', array_keys($items), 'IN')
          ->condition('expire', 0);
        // If there are affected rows, this update succeeded.
        if ($update->execute()) {
          return $items;
        }
      }
      else {
        // No items currently available to claim.
        return [];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function claimItem($lease_time = NULL) {
    throw new \Exception('This queue is designed to process multiple items at once. Please use "claimItems" instead.');
  }

  /**
   * Defines the schema for the queue table.
   */
  public function schemaDefinition() {
    return [
      'description' => 'Drupal entities to push to Salesforce.',
      'fields' => [
        'item_id' => [
          'type' => 'serial',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'description' => 'Primary Key: Unique item ID.',
        ],
        'name' => [
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
          'default' => '',
          'description' => 'The salesforce mapping id',
        ],
        'entity_id' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'The entity id',
        ],
        'mapped_object_id' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'Foreign key for salesforce_mapped_object table.',
        ],
        'op' => [
          'type' => 'varchar_ascii',
          'length' => 16,
          'not null' => TRUE,
          'default' => '',
          'description' => 'The operation which triggered this push',
        ],
        'failures' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'Number of failed push attempts for this queue item.',
        ],
        'expire' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'Timestamp when the claim lease expires on the item.',
        ],
        'created' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'Timestamp when the item was created.',
        ],
        'updated' => [
          'type' => 'int',
          'not null' => TRUE,
          'default' => 0,
          'description' => 'Timestamp when the item was created.',
        ],
      ],
      'primary key' => ['item_id'],
      'unique keys' => [
        'name_entity_id' => ['name', 'entity_id'],
      ],
      'indexes' => [
        'entity_id' => ['entity_id'],
        'name_created' => ['name', 'created'],
        'expire' => ['expire'],
      ],
    ];
  }

  /**
   * Process Salesforce queues.
   */
  public function processQueues($mappings = []) {
    if (empty($mappings)) {
      $mappings = $this
        ->mappingStorage
        ->loadPushMappings();
    }
    if (empty($mappings)) {
      return $this;
    }

    $i = 0;
    foreach ($mappings as $mapping) {
      $i += $this->processQueue($mapping);
      if ($i >= $this->globalLimit) {
        break;
      }
    }
    return $this;
  }

  /**
   * Given a salesforce mapping, process all its push queue entries.
   *
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   Salesforce mapping.
   *
   * @return int
   *   The number of items procesed, or -1 if there was any error, And also
   *   dispatches a SalesforceEvents::ERROR event.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function processQueue(SalesforceMappingInterface $mapping) {
    if (!$this->connection->schema()->tableExists(static::TABLE_NAME)) {
      return 0;
    }
    $this->garbageCollection();
    static $queue_processor = FALSE;
    // Check mapping frequency before proceeding.
    if ($mapping->getNextPushTime() > $this->time->getRequestTime()) {
      return 0;
    }

    if (!$queue_processor) {
      // @TODO push queue processor could be set globally, or per-mapping. Exposing some UI setting would probably be better than this:
      $plugin_name = $this->state->get('salesforce.push_queue_processor', static::DEFAULT_QUEUE_PROCESSOR);
      $queue_processor = $this->queueManager->createInstance($plugin_name);
    }

    $i = 0;
    // Set the queue name, which is the mapping id.
    $this->setName($mapping->id());

    // Iterate through items in this queue (mapping) until we run out or hit
    // the mapping limit, then move to the next queue. If we hit the global
    // limit, return immediately.
    while (TRUE) {
      // Claim as many items as we can from this queue and advance our counter.
      // If this queue is empty, move to the next mapping.
      $items = $this->claimItems($mapping->push_limit, $mapping->push_retries);

      if (empty($items)) {
        $mapping->setLastPushTime($this->time->getRequestTime());
        return $i;
      }

      // Hand them to the queue processor.
      try {
        $queue_processor->process($items);
      }
      catch (RequeueException $e) {
        // Getting a Requeue here is weird for a group of items, but we'll
        // deal with it.
        $this->releaseItems($items);
        $this->eventDispatcher->dispatch(SalesforceEvents::WARNING, new SalesforceErrorEvent($e));
        continue;
      }
      catch (SuspendQueueException $e) {
        // Getting a SuspendQueue is more likely, e.g. because of a network
        // or authorization error. Release items and move on to the next
        // mapping in this case.
        $this->releaseItems($items);
        $this->eventDispatcher->dispatch(SalesforceEvents::WARNING, new SalesforceErrorEvent($e));
        return $i;
      }
      catch (\Exception $e) {
        // In case of any other kind of exception, log it and leave the item
        // in the queue to be processed again later.
        // @TODO: this is how Cron.php queue works, but I don't really understand why it doesn't get re-queued.
        $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e));
      }
      finally {
        // If we've reached our limit, we're done. Otherwise, continue to next
        // items.
        $i += count($items);
        if ($i >= $this->globalLimit) {
          return $i;
        }
      }
    }
    return $i;
  }

  /**
   * {@inheritdoc}
   */
  public function failItem(\Throwable $e, \stdClass $item) {
    $mapping = $this->mappingStorage->load($item->name);

    if ($e instanceof EntityNotFoundException) {
      // If there was an exception loading any entities,
      // we assume that this queue item is no longer relevant.
      $message = 'Exception while loading entity %type %id for salesforce mapping %mapping. Queue item deleted.';
      $args = [
        '%type' => $mapping->get('drupal_entity_type'),
        '%id' => $item->entity_id,
        '%mapping' => $mapping->id(),
      ];
      $this->eventDispatcher->dispatch(SalesforceEvents::NOTICE, new SalesforceNoticeEvent(NULL, $message, $args));
      $this->deleteItem($item);
      return;
    }

    $item->failures++;

    $message = $e->getMessage();
    if ($item->failures >= $this->maxFails) {
      $message = 'Permanently failed queue item %item failed %fail times. Exception while pushing entity %type %id for salesforce mapping %mapping. ' . $message;
    }
    else {
      $message = 'Queue item %item failed %fail times. Exception while pushing entity %type %id for salesforce mapping %mapping. ' . $message;
    }
    $args = [
      '%type' => $mapping->get('drupal_entity_type'),
      '%id' => $item->entity_id,
      '%mapping' => $mapping->id(),
      '%item' => $item->item_id,
      '%fail' => $item->failures,
    ];
    $this->eventDispatcher->dispatch(SalesforceEvents::NOTICE, new SalesforceNoticeEvent(NULL, $message, $args));

    // Failed items will remain in queue, but not be released. They'll be
    // retried only when the current lease expires.
    // doCreateItem() doubles as "save" function.
    $this->doCreateItem(get_object_vars($item));
  }

  /**
   * Same as releaseItem, but for multiple items.
   *
   * @param array $items
   *   Indexes must be item ids. Values are ignored. Return from claimItems()
   *   is acceptable.
   *
   * @return bool
   *   TRUE if the items were released, FALSE otherwise.
   *
   * @throws \Exception
   */
  public function releaseItems(array $items) {
    try {
      $update = $this->connection->update(static::TABLE_NAME)
        ->fields([
          'expire' => 0,
        ])
        ->condition('item_id', array_keys($items), 'IN');
      return $update->execute();
    }
    catch (\Exception $e) {
      $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e));
      $this->catchException($e);
      // If the table doesn't exist we should consider the item released.
      return TRUE;
    }
  }

  /**
   * For a given entity, delete its corresponding queue items.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity whose items should be deleted.
   *
   * @throws \Exception
   */
  public function deleteItemByEntity(EntityInterface $entity) {
    try {
      $this->connection->delete(static::TABLE_NAME)
        ->condition('entity_id', $entity->id())
        ->condition('name', $this->name)
        ->execute();
    }
    catch (\Exception $e) {
      $this->catchException($e);
    }
  }

  /**
   * Uninstall function: cleanup our queue's database table.
   */
  public function deleteTable() {
    $this->connection->schema()->dropTable(static::TABLE_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function garbageCollection() {
    if ($this->garbageCollected) {
      // Prevent excessive garbage collection. We only need it once per request.
      return;
    }
    try {
      // Reset expired items in the default queue implementation table. If
      // that's not used, this will simply be a no-op.
      $this->connection->update(static::TABLE_NAME)
        ->fields([
          'expire' => 0,
        ])
        ->condition('expire', 0, '<>')
        ->condition('expire', $this->time->getRequestTime(), '<')
        ->execute();
      $this->garbageCollected = TRUE;
    }
    catch (\Exception $e) {
      $this->catchException($e);
    }
  }

}
