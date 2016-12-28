<?php

namespace Drupal\salesforce_push;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\SchemaObjectExistsException;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Database\Query\Merge;
use Drupal\Core\Queue\DatabaseQueue;
use Drupal\Core\State\State;

/**
 * Salesforce push queue.
 *
 * @ingroup queue
 */
class PushQueue extends DatabaseQueue {

  /**
   * The database table name.
   */
  const TABLE_NAME = 'salesforce_push_queue';

  const DEFAULT_CRON_PUSH_LIMIT = 200;
  protected $limit;
  protected $connection;
  protected $state;

  /**
   * Constructs a \Drupal\Core\Queue\DatabaseQueue object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The Connection object containing the key-value tables.
   */
  public function __construct(Connection $connection, State $state) {
    $this->connection = $connection;
    $this->state = $state;
    $this->limit = $state->get('salesforce.push_limit', self::DEFAULT_CRON_PUSH_LIMIT);    
  }

  /**
   * Parent class DatabaseQueue relies heavily on $this->name, so it's best to
   * just set the value appropriately.
   *
   * @param string $name
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
   * @return
   *   On success, Drupal\Core\Database\Query\Merge::STATUS_INSERT or Drupal\Core\Database\Query\Merge::STATUS_UPDATE
   *
   * @throws Exception if the required indexes are not provided.
   *
   * @TODO convert $data to a proper class and make sure that's what we get for this argument.
   */
  protected function doCreateItem($data) {
    if (empty($data['name'])
    || empty($data['entity_id'])
    || empty($data['op'])) {
      throw new Exception('Salesforce push queue data values are required for "name", "entity_id" and "op"');
    }
    $this->name = $data['name'];
    $time = time();
    $query = $this->connection->merge(static::TABLE_NAME)
      ->key(array('name' => $this->name, 'entity_id' => $data['entity_id']))
      ->fields(array(
        'name' => $this->name,
        'entity_id' => $data['entity_id'],
        'op' => $data['op'],
        'updated' => $time,
      ));
   
    // Return Merge::STATUS_INSERT or Merge::STATUS_UPDATE
    $ret = $query->execute();

    // Drupal still doesn't support now() https://www.drupal.org/node/215821
    // 9 years.
    if ($ret == Merge::STATUS_INSERT) {
      $this->connection->merge(static::TABLE_NAME)
        ->key(array('name' => $this->name, 'entity_id' => $data['entity_id']))
        ->fields(['created' => $time])
        ->execute();
    }
    return $ret;
  }

  /**
   * Claim $n items from the current queue.
   * @see DatabaseQueue::claimItem
   */
  public function claimItems($n, $lease_time = 30) {
    while (TRUE) {
      try {
        $items = $this->connection->queryRange('SELECT * FROM {' . static::TABLE_NAME . '} q WHERE expire = 0 AND name = :name ORDER BY created, item_id ASC', 0, $n, array(':name' => $this->name))->fetchAllAssoc('entity_id');
      }
      catch (\Exception $e) {
        $this->catchException($e);
        // If the table does not exist there are no items currently available to
        // claim.
        return FALSE;
      }
      if ($items) {
        // Try to update the item. Only one thread can succeed in UPDATEing the
        // same row. We cannot rely on REQUEST_TIME because items might be
        // claimed by a single consumer which runs longer than 1 second. If we
        // continue to use REQUEST_TIME instead of the current time(), we steal
        // time from the lease, and will tend to reset items before the lease
        // should really expire.
        $update = $this->connection->update(static::TABLE_NAME)
          ->fields(array(
            'expire' => time() + $lease_time,
          ))
          ->condition('item_id', array_keys($items), 'IN')
          ->condition('expire', 0);
        // If there are affected rows, this update succeeded.
        if ($update->execute()) {
          return $items;
        }
      }
      else {
        // No items currently available to claim.
        return FALSE;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function claimItem($lease_time = 30) {
    // Claim an item by updating its expire fields. If claim is not successful
    // another thread may have claimed the item in the meantime. Therefore loop
    // until an item is successfully claimed or we are reasonably sure there
    // are no unclaimed items left.
    while (TRUE) {
      try {
        $item = $this->connection->queryRange('SELECT * FROM {' . static::TABLE_NAME . '} q WHERE expire = 0 AND name = :name ORDER BY created, item_id ASC', 0, 1, array(':name' => $this->name))->fetchObject();
      }
      catch (\Exception $e) {
        $this->catchException($e);
        // If the table does not exist there are no items currently available to
        // claim.
        return FALSE;
      }
      if ($item) {
        // Try to update the item. Only one thread can succeed in UPDATEing the
        // same row. We cannot rely on REQUEST_TIME because items might be
        // claimed by a single consumer which runs longer than 1 second. If we
        // continue to use REQUEST_TIME instead of the current time(), we steal
        // time from the lease, and will tend to reset items before the lease
        // should really expire.
        $update = $this->connection->update(static::TABLE_NAME)
          ->fields(array(
            'expire' => time() + $lease_time,
          ))
          ->condition('item_id', $item->item_id)
          ->condition('expire', 0);
        // If there are affected rows, this update succeeded.
        if ($update->execute()) {
          return $item;
        }
      }
      else {
        // No items currently available to claim.
        return FALSE;
      }
    }
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
   * Process Salesforce queues
   */
  public function processQueues() {
    $mappings = salesforce_push_load_push_mappings();
    $i = 0;
    foreach ($mappings as $mapping) {
      $this->setName($mapping->id());

      // @TODO: eventually this should work as follows:
      // - New plugin type "PushQueueProcessor"
      // -- Differs from QueueWorker plugin, because it can choose how to process an entire queue.
      // -- Allows SoapQueueProcessor to optimize queries by processing multiple queue items at once.
      // -- RestQueueProcessor will still do one-at-a-time.
      // - Hand the mapping id (queue name) to the queue processor and let it do its thing
      while (TRUE) {
        if ($this->limit && $i++ > $this->limit) {
          // Global limit is a hard stop. We're done processing now.
          // @TODO some logging about how many items were processed, etc.
          return;
        }

        $item = $this->claimItem();
        if (!$item) {
          // Ran out of items in this queue. Move on to the next one.
          break;
        }

        try {
          $entity = \Drupal::entityTypeManager()
            ->getStorage($mapping->get('drupal_entity_type'))
            ->load($item->entity_id);
          if (!$entity) {
            throw new Exception();
          }
        }
        catch (Exception $e) {
          // If there was an exception loading the entity, we assume that this queue item is no longer relevant.
          \Drupal::logger('Salesforce Push')->notice($e->getMessage() . 
            ' Exception while loading entity %type %id for salesforce mapping %mapping. Queue item deleted.',
            [
              '%type' => $mapping->get('drupal_entity_type'),
              '%id' => $item->entity_id,
              '%mapping' => $mapping->id(),
            ]
          );
          $item->delete();
        }

        try {
          salesforce_push_sync_rest($entity, $mapping, $item->op);
          $this->deleteItem($item);
          \Drupal::logger('Salesforce Push')->notice('Entity %type %id for salesforce mapping %mapping pushed successfully.',
            [
              '%type' => $mapping->get('drupal_entity_type'),
              '%id' => $item->entity_id,
              '%mapping' => $mapping->id(),
            ]
          );
        }
        catch (Exception $e) {
          $item->failure++;
          \Drupal::logger('Salesforce Push')->notice($e->getMessage() . 
            ' Exception while pushing entity %type %id for salesforce mapping %mapping. Queue item %item failed %fail times.',
            [
              '%type' => $mapping->get('drupal_entity_type'),
              '%id' => $item->entity_id,
              '%mapping' => $mapping->id(),
              '%item' => $item->item_id,
              '%fail' => $item->failure,
            ]
          );
          // doCreateItem() doubles as "save" function.
          $item->doCreateItem(get_object_vars($item));
          $this->releaseItem($item);
          // @TODO: push queue processor plugins will have to implement some error tolerance:
          // - If mapped object does not exist, and this is a delete operation, we can delete this queue item.
          // - Otherwise, return item to queue and increment failure count. 
          // - After N failures, move to perma fail table.
        }
      }
    }    
  }

}
