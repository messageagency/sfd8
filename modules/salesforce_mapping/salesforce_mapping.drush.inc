<?php

/**
 * @file
 * Drush integration for Salesforce.
 */

use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Implements hook_drush_command().
 */
function salesforce_mapping_drush_command() {
  $items['sf-prune-revisions'] = [
    'description' => 'Delete old revisions of Mapped Objects, based on revision limit settings. Useful if you have recently changed settings, or if you have just updated to a version with prune support.',
    'aliases' => ['sfprune'],
  ];
  $items['sf-purge-drupal'] = [
    'description' => 'Clean up Mapped Objects table by deleting any records which reference missing Drupal entities.',
    'aliases' => ['sfpd'],
    'options' => [
      'mapping' => 'Given a mapping id, limit purge to only those referenced.',
    ],
  ];
  $items['sf-purge-salesforce'] = [
    'description' => 'Clean up Mapped Objects table by deleting any records which reference missing Salesforce records.',
    'aliases' => ['sfpsf'],
    'options' => [
      'mapping' => 'Given a mapping id, limit purge to only those referenced.',
    ],
  ];
  $items['sf-purge-mapping'] = [
    'description' => 'Clean up Mapped Objects table by deleting any records which reference missing Mappings.',
    'aliases' => ['sfpmap'],
    'options' => [
      'mapping' => 'Given a mapping id, limit purge to only those referenced.',
    ],
  ];
  $items['sf-purge-all'] = [
    'description' => 'Clean up Mapped Objects table by deleting any records which reference missing Mappings, Entities, or Salesforce records.',
    'aliases' => ['sfpall'],
    'options' => [
      'mapping' => 'Given a mapping id, limit purge to only those referenced.',
    ],
  ];
  return $items;
}

/**
 * Support for drush 8 is deprecated and will be removed in a future release.
 */
function drush_salesforce_mapping_sf_prune_revisions() {
  _drush_salesforce_deprecated();

  $limit =
    \Drupal::service('config.factory')
      ->get('salesforce.settings')
      ->get('limit_mapped_object_revisions');
  if ($limit <= 0) {
    drush_log('Mapped Object revisions limit is 0. No action taken.', 'warning');
    return;
  }
  $etm = \Drupal::service('entity_type.manager');
  /** @var \Drupal\salesforce_mapping\MappedObjectStorage $storage */
  $storage = $etm
    ->getStorage('salesforce_mapped_object');
  $revision_table = $etm
    ->getDefinition('salesforce_mapped_object')
    ->getRevisionTable();
  $ids = \Drupal::service('database')
    ->select($revision_table, 'r')
    ->fields('r', ['id'])
    ->having('COUNT(r.id) > ' . $limit)
    ->groupBy('r.id')
    ->execute()
    ->fetchCol();
  if (empty($ids)) {
    drush_log("No Mapped Objects with more than $limit revision(s). No action taken.", 'warning');
    return;
  }
  drush_log('Found ' . count($ids) . ' mapped objects with excessive revisions. Will prune to ' . $limit . ' revision(s) each. This may take a while.', 'ok');
  $total = count($ids);
  $i = 0;
  $buckets = ceil($total / 20);
  if ($buckets == 0) {
    $buckets = 1;
  }
  foreach ($ids as $id) {
    if ($i++ % $buckets == 0) {
      drush_log("Pruned $i of $total records.", 'ok');
    }
    /** @var \Drupal\salesforce_mapping\Entity\MappedObject $mapped_object */
    if ($mapped_object = $storage->load($id)) {
      $mapped_object->pruneRevisions($storage);
    }
  }

}

/**
 * Support for drush 8 is deprecated and will be removed in a future release.
 */
function drush_salesforce_mapping_sf_purge_drupal() {
  _drush_salesforce_deprecated();
  $etm = \Drupal::service('entity_type.manager');
  $mapped_obj_storage = $etm->getStorage('salesforce_mapped_object');
  $mapped_obj_table = $etm
    ->getDefinition('salesforce_mapped_object')
    ->getBaseTable();

  $query = \Drupal::service('database')
    ->select($mapped_obj_table, 'm')
    ->fields('m', ['drupal_entity__target_type'])
    ->distinct();
  if ($mapping_id = drush_get_option('mapping')) {
    $query->condition('salesforce_mapping', $mapping_id);
  }
  $entity_type_ids = $query
    ->execute()
    ->fetchCol();
  if (empty($entity_type_ids)) {
    drush_log('No orphaned mapped objects found by Drupal entities.', 'ok');
    return;
  }

  foreach ($entity_type_ids as $et_id) {
    $query = \Drupal::service('database')
      ->select($mapped_obj_table, 'm')
      ->fields('m', ['id'])
      ->condition('drupal_entity__target_type', $et_id);

    $entity_type = $etm->getDefinition($et_id);
    if ($entity_type) {
      $id_key = $entity_type->getKey('id');
      $query->addJoin("LEFT", $entity_type->getBaseTable(), 'et', "et.$id_key = m.drupal_entity__target_id_int");
      $query->isNull("et.$id_key");
    }
    $mapped_obj_ids = $query->execute()->fetchCol();
    if (empty($mapped_obj_ids)) {
      drush_log('No orphaned mapped objects found for ' . $et_id . '.', 'ok');
      continue;
    }
    _drush_salesforce_mapping_confirm_and_delete($mapped_obj_ids, $mapped_obj_storage, 'entity type: ' . $et_id);
  }
}

/**
 * Support for drush 8 is deprecated and will be removed in a future release.
 */
function drush_salesforce_mapping_sf_purge_salesforce() {
  _drush_salesforce_deprecated();
  $etm = \Drupal::service('entity_type.manager');
  $client = \Drupal::service('salesforce.client');
  $object_types = _drush_salesforce_mapping_object_types_by_prefix($client);

  $mapped_obj_storage = $etm->getStorage('salesforce_mapped_object');
  $mapped_obj_table = $etm
    ->getDefinition('salesforce_mapped_object')
    ->getBaseTable();

  $query = \Drupal::service('database')
    ->select($mapped_obj_table, 'm');
  $query->addExpression('distinct substr(salesforce_id, 1, 3)');
  $mapping_id = FALSE;
  if ($mapping_id = drush_get_option('mapping')) {
    $query->condition('salesforce_mapping', $mapping_id);
  }
  $sfid_prefixes = $query
    ->execute()
    ->fetchCol();

  foreach ($sfid_prefixes as $prefix) {
    if (empty($object_types[$prefix]['name'])) {
      $mapped_obj_ids = \Drupal::service('database')
        ->select($mapped_obj_table, 'm')
        ->fields('m', ['salesforce_id', 'id'])
        ->condition('salesforce_id', $prefix . '%', 'LIKE')
        ->execute()
        ->fetchAllKeyed();
      if (empty($mapped_obj_ids)) {
        continue;
      }
      drush_log('Unknown object type for Salesforce ID prefix ' . $prefix);
      _drush_salesforce_mapping_confirm_and_delete($mapped_obj_ids, $mapped_obj_storage, 'prefix ' . $prefix);
      continue;
    }
    $query = \Drupal::service('database')
      ->select($mapped_obj_table, 'm')
      ->fields('m', ['salesforce_id', 'id']);
    if ($mapping_id) {
      $query->condition('salesforce_mapping', $mapping_id);
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
      $results = $client->query($soql_query);
      foreach ($results->records() as $record) {
        unset($to_delete[(string) $record->id()]);
      }
    }
    if (empty($to_delete)) {
      drush_log('No orphaned mapped objects found for SObject type ' . $object_types[$prefix]['name'], 'ok');
      continue;
    }
    _drush_salesforce_mapping_confirm_and_delete(array_values($to_delete), $mapped_obj_storage, 'SObject type *' . $object_types[$prefix]['name'] . '*');
  }
}

/**
 * Support for drush 8 is deprecated and will be removed in a future release.
 */
function drush_salesforce_mapping_sf_purge_mapping() {
  _drush_salesforce_deprecated();
  $etm = \Drupal::service('entity_type.manager');
  $mapped_obj_storage = $etm->getStorage('salesforce_mapped_object');
  $mapped_obj_table = $etm
    ->getDefinition('salesforce_mapped_object')
    ->getBaseTable();

  $query = \Drupal::service('database')
    ->select($mapped_obj_table, 'm')
    ->fields('m', ['salesforce_mapping'])
    ->distinct();
  if ($mapping_id = drush_get_option('mapping')) {
    $query->condition('salesforce_mapping', $mapping_id);
  }
  $mapping_ids = $query
    ->execute()
    ->fetchCol();
  if (empty($entity_type_ids)) {
    drush_log('No orphaned mapped objects found by mapping.', 'ok');
    return;
  }

  foreach ($mapping_ids as $mapping_id) {
    $mapping = $etm
      ->getStorage('salesforce_mapping')
      ->load($mapping_id);
    // If mapping loads successsfully, we assume the mapped object is OK.
    if ($mapping) {
      continue;
    }
    $mapped_obj_ids = \Drupal::service('database')
      ->select($mapped_obj_table, 'm')
      ->fields('m', ['id'])
      ->condition('salesforce_mapping', $mapping_id)
      ->distinct()
      ->execute()
      ->fetchCol();

    _drush_salesforce_mapping_confirm_and_delete($mapped_obj_ids, $mapped_obj_storage, 'missing mapping: ' . $mapping_id);
  }
}

/**
 * Support for drush 8 is deprecated and will be removed in a future release.
 */
function drush_salesforce_mapping_sf_purge_all() {
  _drush_salesforce_deprecated();
  drush_salesforce_mapping_sf_purge_mapping();
  drush_salesforce_mapping_sf_purge_drupal();
  drush_salesforce_mapping_sf_purge_salesforce();
}

/**
 * Helper to fetch object types by prefix.
 *
 * @param \Drupal\salesforce\Rest\RestClientInterface $client
 *   Client interface.
 *
 * @return array
 *   Array of objects, indexed by key prefix.
 */
function _drush_salesforce_mapping_object_types_by_prefix(RestClientInterface $client) {
  $ret = [];
  $describe = $client->objects();
  foreach ($describe as $object) {
    $ret[$object['keyPrefix']] = $object;
  }
  return $ret;
}

/**
 * Helper to interactively confirm delete.
 *
 * @param array $object_ids
 *   Records to be deleted.
 * @param \Drupal\Core\Entity\ContentEntityStorageInterface $storage
 *   Storage.
 * @param string $extra
 *   Extra message parts.
 *
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function _drush_salesforce_mapping_confirm_and_delete(array $object_ids, ContentEntityStorageInterface $storage, $extra = '') {
  if (empty($object_ids)) {
    return;
  }
  $message = 'Delete ' . count($object_ids) . ' orphaned mapped objects';
  if ($extra) {
    $message .= ' for ' . $extra;
  }
  $message .= '?';
  if (!drush_confirm($message)) {
    return;
  }

  // Still have to *load* entities in order to delete them. **UGH**.
  $mapped_objs = $storage->loadMultiple($object_ids);
  $storage->delete($mapped_objs);
}
