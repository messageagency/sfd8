<?php

/**
 * @file
 * Drush integration for Salesforce.
 */

use Drupal\salesforce\SFID;
use Drupal\salesforce\SelectQuery;

/**
 * Implements hook_drush_command().
 */
function salesforce_mapping_drush_command() {
  $items['sf-prune-revisions'] = [
    'description' => 'Delete old revisions of Mapped Objects, based on revision limit settings. Useful if you have recently changed settings, or if you have just updated to a version with prune support.',
    'aliases' => ['sfprune'],
  ];
  return $items;
}

function drush_salesforce_mapping_sf_prune_revisions() {
  $limit =
    \Drupal::service('config.factory')
    ->get('salesforce.settings')
    ->get('limit_mapped_object_revisions');
  if ($limit <= 0) {
    drush_log('Mapped Object revisions limit is 0. No action taken.', 'warning');
    return;
  }
  $etm = \Drupal::service('entity_type.manager');
  $storage = $etm
    ->getStorage('salesforce_mapped_object');
  $table = $etm
    ->getDefinition('salesforce_mapped_object')
    ->getBaseTable();
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
    $mapped_object = $storage->load($id);
    $mapped_object->pruneRevisions($storage);
  }
  
}