<?php

namespace Drupal\salesforce_push;

use Drupal\Core\Queue\DatabaseQueue;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\SchemaObjectExistsException;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Database\Query\Merge;

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

  /**
   * Constructs a \Drupal\Core\Queue\DatabaseQueue object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The Connection object containing the key-value tables.
   */
  function __construct(Connection $connection) {
    $this->connection = $connection;
  }

  public function setName($name) {
    $this->name = $name;
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
        $items = $this->connection->queryRange('SELECT name, entity_id, op, created, item_id FROM {' . static::TABLE_NAME . '} q WHERE expire = 0 AND name = :name ORDER BY created, item_id ASC', 0, $n, array(':name' => $this->name))->fetchAllAssoc('entity_id');
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

    foreach ($mappings as $mapping) {
      // @TODO: Implement a global limit for REST async. Limit per mapping doesn't make sense here since we're doing one entry at a time.
      $this->setName($mapping->id());

      // @TODO this is where we would be branching for SOAP vs REST async push. How to encapsulate this? Delegate to queue worker?
      while ($item = $this->claimItem()) {
        try {
          $entity = \Drupal::entityTypeManager()
            ->getStorage($mapping->get('drupal_entity_type'))
            ->load($item->entity_id);

          // @TODO this doesn't feel right. Where should this go?
          salesforce_push_sync_rest($entity, $mapping, $item->op);
        }
        catch (Exception $e) {
          // @TODO on Exception, mapped object was unable to be created or updated, and operation was not undertaken.
          // If mapped does not exist, and this is a delete operation, we can delete this queue item.
          // Otherwise, return item to queue and increment failure count. 
          // After N failures, move to perma fail table.
        }
      }
    }    
  }

}
