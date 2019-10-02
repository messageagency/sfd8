<?php

namespace Drupal\salesforce_push\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\salesforce_mapping\Commands\SalesforceMappingCommandsBase;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\salesforce_push\PushQueue;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;
use Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface;
use Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface;

/**
 * A Drush commandfile.
 *
 * In addition to this file, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class SalesforcePushCommands extends SalesforceMappingCommandsBase {

  /**
   * Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Push queue service.
   *
   * @var \Drupal\salesforce_push\PushQueue
   */
  protected $pushQueue;

  /**
   * SalesforcePushCommands constructor.
   *
   * @param \Drupal\salesforce\Rest\RestClient $client
   *   Salesforce service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   ETM service.
   * @param \Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface $auth_man
   *   Auth plugin manager.
   * @param \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface $token_storage
   *   Token storage.
   * @param \Drupal\salesforce_push\PushQueue $pushQueue
   *   Push queue service.
   * @param \Drupal\Core\Database\Connection $database
   *   Database service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(RestClient $client, EntityTypeManagerInterface $etm, SalesforceAuthProviderPluginManagerInterface $auth_man, SalesforceAuthTokenStorageInterface $token_storage, PushQueue $pushQueue, Connection $database) {
    parent::__construct($client, $etm, $auth_man, $token_storage);
    $this->pushQueue = $pushQueue;
    $this->database = $database;
  }

  /**
   * Collect a mapping interactively.
   *
   * @hook interact salesforce_push:push-queue
   */
  public function interactPushQueue(Input $input, Output $output) {
    return $this->interactPushMappings($input, $output, 'Choose a Salesforce mapping', 'Push All');
  }

  /**
   * Collect a mapping interactively.
   *
   * @hook interact salesforce_push:push-unmapped
   */
  public function interactPushUnmapped(Input $input, Output $output) {
    return $this->interactPushMappings($input, $output, 'Choose a Salesforce mapping', 'Push All');
  }

  /**
   * Collect a mapping interactively.
   *
   * @hook interact salesforce_push:requeue
   */
  public function interactRequeue(Input $input, Output $output) {
    return $this->interactPushMappings($input, $output, 'Choose a Salesforce mapping', 'Push All');
  }

  /**
   * Process push queues for one or all Salesforce Mappings.
   *
   * @param string $name
   *   Mapping name.
   *
   * @throws \Exception
   *
   * @usage drush sfpushq
   *   Process all push queue items.
   * @usage drush sfpushq foo
   *   Process push queue items for mapping "foo".
   *
   * @command salesforce_push:push-queue
   * @aliases sfpushq,sfpm,sf-push-queue,salesforce_push:queue
   */
  public function pushQueue($name) {
    $mappings = $this->getPushMappingsFromName($name);
    foreach ($mappings as $mapping) {
      // Process one mapping queue.
      $this->pushQueue->processQueue($mapping);
      $this->logger()->info(dt('Finished pushing !name', ['!name' => $mapping->label()]));
    }
  }

  /**
   * Requeue mapped entities for asynchronous push.
   *
   * Addresses the frequent need to re-push all entities for a given mapping.
   * Given a mapping, re-queue all the mapped objects to the Salesforce push
   * queue. The push queue will not be processed by this command, and no data
   * will be pushed to salesforce. Run salesforce_push:push-queue to proceess
   * the records queued by this command.
   *
   * NOTE: Existing push queue records will be replaced by this operation.
   *
   * @param string $name
   *   The Drupal machine name of the mapping for the entities.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option ids
   *   If provided, only requeue the entities given by these ids.
   *   Comma-delimited.
   * @usage drush sfpu foo
   *   Requeue all drupal entities mapped objects for mapping "foo".
   * @usage drush sfpu foo --ids=1,2,3,4
   *   Requeue entities for mapping "foo" with ids 1, 2, 3, 4, if they exist.
   *
   * @command salesforce_push:requeue
   * @aliases sfrq,salesforce-push-requeue
   * @see salesforce_push:push-queue
   */
  public function requeue($name, array $options = ['ids' => '']) {
    // Dummy call to create item, to ensure table exists.
    try {
      \Drupal::service('queue.salesforce_push')->createItem(NULL);
    }
    catch (\Exception $e) {

    }
    $mappings = $this->getPushMappingsFromName($name);
    foreach ($mappings as $mapping) {
      $ids = array_filter(array_map('intval', explode(',', $options['ids'])));
      $mapping_name = $mapping->id();
      $op = MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_UPDATE;
      $time = time();
      $insertQuery = "REPLACE INTO salesforce_push_queue (name, entity_id, mapped_object_id, op, failures, expire, created, updated) 
          (SELECT '$mapping_name', drupal_entity__target_id, id, '$op', 0, 0, $time, $time FROM salesforce_mapped_object WHERE salesforce_mapping = '$mapping_name' ";
      if (!empty($ids)) {
        $insertQuery .= " AND drupal_entity__target_id IN (" . implode(',', $ids) . ")";
      }
      $insertQuery .= ")";
      $this->database->query($insertQuery)->execute();
    }
  }

  /**
   * Push entities of a mapped type that are not linked to Salesforce Objects.
   *
   * @param string $name
   *   The Drupal machine name of the mapping for the entities.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @option count
   *   The number of entities to try to sync. (Default is 50).
   * @usage drush sfpu foo
   *   Push 50 drupal entities without mapped objects for mapping "foo"
   * @usage drush sfpu foo --count=42
   *   Push 42 unmapped drupal entities without mapped objects for mapping "foo"
   *
   * @command salesforce_push:push-unmapped
   * @aliases sfpu,salesforce-push-unmapped,salesforce_push:unmapped
   */
  public function pushUnmapped($name, array $options = ['count' => 50]) {
    $mappings = $this->getPushMappingsFromName($name);
    foreach ($mappings as $mapping) {
      $entity_type = $mapping->get('drupal_entity_type');
      $entity_storage = $this->etm->getStorage($entity_type);
      $entity_keys = $this->etm->getDefinition($entity_type)->getKeys();
      $id_key = $entity_keys['id'];
      $bundle_key = empty($entity_keys['bundle']) ? FALSE : $entity_keys['bundle'];
      $query = $this->database->select($entity_storage->getBaseTable(), 'b');
      $query->leftJoin('salesforce_mapped_object', 'm', "b.$id_key = m.drupal_entity__target_id AND m.drupal_entity__target_type = '$entity_type'");
      if ($bundle_key) {
        $query->condition("b.$bundle_key", $mapping->get('drupal_bundle'));
      }
      $query->fields('b', [$id_key]);
      $query->isNull('m.drupal_entity__target_id');
      $results = $query->range(0, $options['count'])
        ->execute()
        ->fetchAllAssoc($id_key);
      $entities = $entity_storage->loadMultiple(array_keys($results));
      $log = [];
      foreach ($entities as $entity) {
        salesforce_push_entity_crud($entity, 'push_create');
        $log[] = $entity->id();
      }
      $this->logger->info(dt("!mapping: !count unmapped entities found and push to Salesforce attempted. See logs for more details.", ['!count' => count($log), '!mapping' => $mapping->label()]));
    }
  }

}
