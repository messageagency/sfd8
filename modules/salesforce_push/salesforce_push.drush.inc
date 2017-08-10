<?php

/**
 * @file
 * Drush integration for Salesforce.
 */

/**
 * Implements hook_drush_command().
 */
function salesforce_push_drush_command() {
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

  return $items;
}

function drush_salesforce_push_sf_push_queue($name = NULL) {
  $queue = \Drupal::service('queue.salesforce_push');
  if ($name !== NULL) {
    if (!($mapping = _salesforce_drush_get_mapping($name))) {
      return;
    }
    // Process one mapping queue
    $queue->processQueue($mapping);
  }
  else {
    // Process all queues
    $queue->processQueues();
  }
}