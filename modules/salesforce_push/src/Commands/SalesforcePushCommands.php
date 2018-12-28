<?php

namespace Drupal\salesforce_push\Commands;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\salesforce_mapping\Commands\SalesforceMappingCommandsBase;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce_push\PushQueue;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;

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
   * @param \Drupal\salesforce_push\PushQueue $pushQueue
   *   Push queue service.
   * @param \Drupal\Core\Database\Connection $database
   *   Database service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(RestClient $client, EntityTypeManagerInterface $etm, PushQueue $pushQueue, Connection $database) {
    parent::__construct($client, $etm);
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
   * Process push queues for one or all Salesforce Mappings.
   *
   * @param string $name
   *   Mapping name.
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
   * Push entities of a mapped type that are not linked to Salesforce Objects.
   *
   * @param string $name
   *   The Drupal machine name of the mapping for the entities.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
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
  public function pushUnmapped($name, array $options = ['count' => NULL]) {
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
      $results = $query->range(0, drush_get_option('count', 50))
        ->execute()
        ->fetchAllAssoc($id_key);
      $entities = $entity_storage->loadMultiple(array_keys($results));
      $log = [];
      print_r($entities);
      foreach ($entities as $entity) {
        salesforce_push_entity_crud($entity, 'push_create');
        $log[] = $entity->id();
      }
      $this->logger->info(dt("!mapping: !count unmapped entities found and push to Salesforce attempted. See logs for more details.", ['!count' => count($log), '!mapping' => $mapping->label()]));
    }
  }

}
