<?php

/**
 * @file
 * Drush tools for the Salesforce Push module.
 */

/**
 * Implements hook_drush_command().
 */
function salesforce_push_drush_command() {
  $items = [];

  $items['sf-push-queue'] = [
    'category' => 'salesforce',
    'description' => 'Process push queues (as though during cron) for one or all Salesforce Mappings.',
    'aliases' => ['sfpushq', 'sfpm'],
    'arguments' => [
      'name' => [
        'description' => 'Machine name of the Salesforce Mapping for which to process push queue. If omitted, process all queues.',
      ],
    ],
    'examples' => [
      'drush sfpushq' => 'Process all push queue items',
      'drush sfpushq foo' => 'Process push queue items for mapping "foo"',
    ],
  ];

  // Push entities in a mapping that have not yet been pushed.
  $items['salesforce-push-unmapped'] = [
    'description' => "Attempt to push entities of a mapped type that are not linked to Salesforce Objects.",
    'aliases' => ['sfpu'],
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_FULL,
    'core' => ['8+'],
    'arguments' => [
      'mapping_id' => 'The Drupal machine name of the mapping for the entities.',
    ],
    'options' => [
      'count' => [
        'description' => 'The number of entities to try to sync. (Default is 50).',
        'example-value' => 42,
      ],
    ],
    'examples' => [
      'drush sfpu foo' => 'Push 50 drupal entities without mapped objects for mapping "foo"',
      'drush sfpu foo --count=42' => 'Push 42 unmapped drupal entities without mapped objects for mapping "foo"',
    ],
  ];

  return $items;
}

/**
 * Implements drush_hook_COMMAND().
 */
function drush_salesforce_push_sf_push_queue($name = NULL) {
  _drush_salesforce_deprecated();
  $queue = \Drupal::service('queue.salesforce_push');
  if ($name !== NULL) {
    if (!($mapping = _salesforce_drush_get_mapping($name))) {
      return;
    }
    // Process one mapping queue.
    $queue->processQueue($mapping);
  }
  else {
    // Process all queues.
    $queue->processQueues();
  }
}

/**
 * Implements drush_hook_COMMAND().
 *
 * Search for entities without Salesforce Object mappings and attempt to push
 * them to Salesforce.
 */
function drush_salesforce_push_unmapped($name = NULL) {
  _drush_salesforce_deprecated();
  if (!($mapping = _salesforce_drush_get_mapping($name))) {
    return;
  }

  $entity_type = $mapping->get('drupal_entity_type');
  $etm = \Drupal::entityTypeManager();
  $entity_storage = $etm->getStorage($entity_type);
  $entity_keys = $etm->getDefinition($entity_type)->getKeys();
  $id_key = $entity_keys['id'];
  $bundle_key = empty($entity_keys['bundle']) ? FALSE : $entity_keys['bundle'];
  $query = \Drupal::database()->select($entity_storage->getBaseTable(), 'b');
  $query->leftJoin('salesforce_mapped_object', 'm', "b.$id_key = m.drupal_entity__target_id AND m.drupal_entity__target_type = '$entity_type'");
  if ($bundle_key) {
    $query->condition("b.$bundle_key", $mapping->get('drupal_bundle'));
  }
  $results = $query
    ->fields('b', [$id_key])
    ->isNull('m.drupal_entity__target_id')
    ->range(0, drush_get_option('count', 50))
    ->execute()
    ->fetchAllAssoc($id_key);
  $entities = $entity_storage->loadMultiple(array_keys($results));
  $log = [];
  foreach ($entities as $entity) {
    salesforce_push_entity_crud($entity, 'push_create');
    $log[] = $entity->id();
  }
  drush_print(count($log) . " unmapped entities found and push to Salesforce attempted. See logs for more details.");
}
