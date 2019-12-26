<?php

namespace Drupal\salesforce_mapping\Commands;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce\SelectQuery;
use Drush\Exceptions\UserAbortException;
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
class SalesforceMappingCommands extends SalesforceMappingCommandsBase {

  /**
   * Salesforce settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $salesforceConfig;

  /**
   * Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * SalesforceMappingCommands constructor.
   *
   * @param \Drupal\salesforce\Rest\RestClient $client
   *   The salesforce.client service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   The entity_type.manager service.
   * @param \Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface $auth_man
   *   Auth plugin manager.
   * @param \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface $token_storage
   *   Token storage.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The config.factory service.
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(RestClient $client, EntityTypeManagerInterface $etm, SalesforceAuthProviderPluginManagerInterface $auth_man, SalesforceAuthTokenStorageInterface $token_storage, ConfigFactory $configFactory, Connection $database) {
    parent::__construct($client, $etm, $auth_man, $token_storage);
    $this->database = $database;
    $this->salesforceConfig = $configFactory->get('salesforce.settings');
  }

  /**
   * Get a limit argument interactively.
   *
   * @hook interact salesforce_mapping:prune-revisions
   */
  public function interactPrune(Input $input, Output $output) {
    if ($input->getArgument('limit')) {
      return;
    }
    $config_limit = $this->salesforceConfig->get('limit_mapped_object_revisions');
    // These 2 lines give different results:
    while (TRUE) {
      if (!$limit = $this->io()->ask('Enter a revision limit (integer). All revisions beyond this limit will be deleted, oldest first', $config_limit)) {
        throw new UserAbortException();
      }
      elseif ($limit > 0) {
        $input->setArgument('limit', $limit);
        return;
      }
      else {
        $this->logger()->error('A positive integer limit is required.');
      }
    }
  }

  /**
   * Delete old revisions of Mapped Objects, based on revision limit settings.
   *
   * Useful if you have recently changed settings, or if you have just updated
   * to a version with prune support.
   *
   * @param int $limit
   *   If $limit is not specified,
   *   salesforce.settings.limit_mapped_object_revisions is used.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @command salesforce_mapping:prune-revisions
   * @aliases sfprune,sf-prune-revisions
   */
  public function pruneRevisions($limit) {
    $revision_table = $this->etm
      ->getDefinition('salesforce_mapped_object')
      ->getRevisionTable();
    $ids = $this->database
      ->select($revision_table, 'r')
      ->fields('r', ['id'])
      ->having('COUNT(r.id) > ' . $limit)
      ->groupBy('r.id')
      ->execute()
      ->fetchCol();
    if (empty($ids)) {
      $this->logger()->warning(dt("No Mapped Objects with more than !limit revision(s). No action taken.", ['!limit' => $limit]));
      return;
    }
    $this->logger()->info(dt('Found !count mapped objects with excessive revisions. Will prune to revision(s) each. This may take a while.', ['!count' => count($ids), '!limit' => $limit]));
    $total = count($ids);
    $i = 0;
    $buckets = ceil($total / 20);
    if ($buckets == 0) {
      $buckets = 1;
    }
    foreach ($ids as $id) {
      if ($i++ % $buckets == 0) {
        $this->logger()->info(dt("Pruned !i of !total records.", ['!i' => $i, '!total' => $total]));
      }
      /** @var \Drupal\salesforce_mapping\Entity\MappedObject $mapped_object */
      if ($mapped_object = $this->mappedObjectStorage->load($id)) {
        $mapped_object->pruneRevisions($this->mappedObjectStorage);
      }
    }
  }

  /**
   * Interactively gather a salesforce mapping name.
   *
   * @hook interact salesforce_mapping:purge-drupal
   */
  public function interactPurgeDrupal(Input $input, Output $output) {
    return $this->interactMapping($input, $output, 'Choose a Salesforce mapping', 'Purge All');
  }

  /**
   * Interactively gather a salesforce mapping name.
   *
   * @hook interact salesforce_mapping:purge-salesforce
   */
  public function interactPurgeSalesforce(Input $input, Output $output) {
    return $this->interactMapping($input, $output, 'Choose a Salesforce mapping', 'Purge All');
  }

  /**
   * Interactively gather a salesforce mapping name.
   *
   * @hook interact salesforce_mapping:purge-mapping
   */
  public function interactPurgeMapping(Input $input, Output $output) {
    return $this->interactMapping($input, $output, 'Choose a Salesforce mapping', 'Purge All');
  }

  /**
   * Interactively gather a salesforce mapping name.
   *
   * @hook interact salesforce_mapping:purge-all
   */
  public function interactPurgeAll(Input $input, Output $output) {
    return $this->interactMapping($input, $output, 'Choose a Salesforce mapping', 'Purge All');
  }

  /**
   * Clean up Mapped Objects referencing missing Drupal entities.
   *
   * @param string $name
   *   Id of the salesforce mapping whose mapped objects should be purged.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @command salesforce_mapping:purge-drupal
   * @aliases sfpd,sf-purge-drupal
   */
  public function purgeDrupal($name) {
    $mapped_obj_table = $this->etm
      ->getDefinition('salesforce_mapped_object')
      ->getBaseTable();

    $query = $this->database
      ->select($mapped_obj_table, 'm')
      ->fields('m', ['drupal_entity__target_type'])
      ->distinct();
    if ($name && strtoupper($name) != 'ALL') {
      $query->condition('salesforce_mapping', $name);
    }
    $entity_type_ids = $query
      ->execute()
      ->fetchCol();
    if (empty($entity_type_ids)) {
      $this->logger()->info('No orphaned mapped objects found by Drupal entities.');
      return;
    }

    foreach ($entity_type_ids as $et_id) {
      $query = $this->database
        ->select($mapped_obj_table, 'm')
        ->fields('m', ['id']);
      $query->condition('drupal_entity__target_type', $et_id);

      $entity_type = $this->etm->getDefinition($et_id);
      if ($entity_type) {
        $id_key = $entity_type->getKey('id');
        $query->addJoin("LEFT", $entity_type->getBaseTable(), 'et', "et.$id_key = m.drupal_entity__target_id_int");
        $query->isNull("et.$id_key");
      }
      $mapped_obj_ids = $query->execute()->fetchCol();
      if (empty($mapped_obj_ids)) {
        $this->logger()->info('No orphaned mapped objects found for ' . $et_id . '.');
        continue;
      }
      $this->purgeConfirmAndDelete($mapped_obj_ids, 'entity type: ' . $et_id);
    }
  }

  /**
   * Helper to confirm before destructive operation.
   */
  protected function purgeConfirmAndDelete(array $object_ids, $extra = '') {
    if (empty($object_ids)) {
      return;
    }
    $message = 'Delete ' . count($object_ids) . ' orphaned mapped objects';
    if ($extra) {
      $message .= ' for ' . $extra;
    }
    $message .= '?';
    if (!$this->io()->confirm($message)) {
      return;
    }

    // Still have to *load* entities in order to delete them. **UGH**.
    $mapped_objs = $this->mappedObjectStorage->loadMultiple($object_ids);
    $this->mappedObjectStorage->delete($mapped_objs);
  }

  /**
   * Helper to gather object types by prefix.
   */
  protected function objectTypesByPrefix() {
    $ret = [];
    $describe = $this->client->objects();
    foreach ($describe as $object) {
      $ret[$object['keyPrefix']] = $object;
    }
    return $ret;
  }

  /**
   * Clean up Mapped Objects by deleting records referencing missing records.
   *
   * @param string $name
   *   Id of the salesforce mapping whose mapped objects should be purged.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @command salesforce_mapping:purge-salesforce
   * @aliases sfpsf,sf-purge-salesforce
   */
  public function purgeSalesforce($name) {
    $object_types = $this->objectTypesByPrefix();
    $mapped_obj_table = $this->etm
      ->getDefinition('salesforce_mapped_object')
      ->getBaseTable();

    $query = $this->database->select($mapped_obj_table, 'm');
    $query->addExpression('distinct substr(salesforce_id, 1, 3)');

    if ($name && strtoupper($name) != 'ALL') {
      $query->condition('salesforce_mapping', $name);
    }
    $sfid_prefixes = $query
      ->execute()
      ->fetchCol();

    foreach ($sfid_prefixes as $prefix) {
      if (empty($object_types[$prefix]['name'])) {
        $query = $this->database
          ->select($mapped_obj_table, 'm')
          ->fields('m', ['salesforce_id', 'id']);
        $query->condition('salesforce_id', $prefix . '%', 'LIKE');
        $mapped_obj_ids = $query
          ->execute()
          ->fetchAllKeyed();
        if (empty($mapped_obj_ids)) {
          continue;
        }
        $this->logger()->warning(dt('Unknown object type for Salesforce ID prefix !prefix', ['!prefix' => $prefix]));
        $this->purgeConfirmAndDelete($mapped_obj_ids, 'prefix ' . $prefix);
        continue;
      }
      $query = $this->database
        ->select($mapped_obj_table, 'm')
        ->fields('m', ['salesforce_id', 'id']);
      if ($name && strtoupper($name) != 'ALL') {
        $query->condition('salesforce_mapping', $name);
      }
      else {
        $query->condition('salesforce_id', $prefix . '%', 'LIKE');
      }
      $sfids = $query
        ->execute()
        ->fetchAllKeyed();
      $to_delete = $sfids;
      // SOQL queries are limited to 4000-characters in where statements.
      // Chunkify in case we have more than ~200 sfids.
      foreach (array_chunk($sfids, 200, TRUE) as $chunk) {
        $soql_query = new SelectQuery($object_types[$prefix]['name']);
        $soql_query->fields[] = 'Id';
        $soql_query->addCondition('Id', array_keys($chunk));
        $results = $this->client->query($soql_query);
        foreach ($results->records() as $record) {
          unset($to_delete[(string) $record->id()]);
        }
      }
      if (empty($to_delete)) {
        $this->logger()->info(dt('No orphaned mapped objects found for SObject type !type', ['!type' => $object_types[$prefix]['name']]));
        continue;
      }
      $this->purgeConfirmAndDelete(array_values($to_delete), 'SObject type *' . $object_types[$prefix]['name'] . '*');
    }
  }

  /**
   * Clean up Mapped Objects by deleting records referencing missing Mappings.
   *
   * @param string $name
   *   Mapping id.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @command sf:purge-mapping
   * @aliases sfpmap,sf-purge-mapping
   */
  public function purgeMapping($name) {
    $mapped_obj_table = $this->etm
      ->getDefinition('salesforce_mapped_object')
      ->getBaseTable();

    $query = $this->database
      ->select($mapped_obj_table, 'm')
      ->fields('m', ['salesforce_mapping'])
      ->distinct();
    if ($name && strtoupper($name) != 'ALL') {
      $query->condition('salesforce_mapping', $name);
    }
    $mapping_ids = $query
      ->execute()
      ->fetchCol();
    if (empty($entity_type_ids)) {
      $this->logger()->info('No orphaned mapped objects found by mapping.');
      return;
    }

    foreach ($mapping_ids as $mapping_id) {
      $mapping = $this->mappingStorage->load($mapping_id);
      // If mapping loads successsfully, we assume the mapped object is OK.
      if ($mapping) {
        continue;
      }
      $query = $this->database
        ->select($mapped_obj_table, 'm')
        ->fields('m', ['id']);
      $query->condition('salesforce_mapping', $mapping_id);
      $mapped_obj_ids = $query->distinct()
        ->execute()
        ->fetchCol();
      $this->purgeConfirmAndDelete($mapped_obj_ids, 'missing mapping: ' . $mapping_id);
    }
  }

  /**
   * Clean up Mapped Objects table.
   *
   * Clean by deleting any records which reference missing Mappings, Entities,
   * or Salesforce records.
   *
   * @param string $name
   *   Id of the salesforce mapping whose mapped objects should be purged.
   *
   * @command salesforce_mapping:purge-all
   * @aliases sfpall,sf-purge-all
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function purgeAll($name) {
    $this->purgeDrupal($name);
    $this->purgeSalesforce($name);
    $this->purgeMapping($name);
  }

}
