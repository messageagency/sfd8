<?php

/**
 * @file
 * Salesforce Pull drush 8 commands.
 */

use Drupal\salesforce\SFID;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce_mapping\Event\SalesforceQueryEvent;

/**
 * Implements hook_drush_command().
 */
function salesforce_pull_drush_command() {

  $items['sf-pull-query'] = [
    'category' => 'salesforce',
    'description' => 'Given a mapping, enqueue records for pull from Salesforce, ignoring modification timestamp. This command is useful, for example, when seeding content for a Drupal site prior to deployment.',
    'aliases' => ['sfpq', 'sfiq'],
    'arguments' => [
      'name' => 'Machine name of the Salesforce Mapping for which to queue pull records.',
    ],
    'options' => [
      'where' => [
        'description' => 'A WHERE clause to add to the SOQL pull query. Default behavior is to query and pull all records.',
      ],
      'start' => 'strtotime()able string for the start timeframe over which to pull, e.g. "-5 hours". If omitted, use the value given by the mapping\'s pull timestamp. Must be in the past.',
      'stop' => 'strtotime()able string for the end timeframe over which to pull, e.g. "-5 hours". If omitted, defaults to "now". Must be "now" or earlier',
      'force-pull' => 'if given, force all queried records to be pulled regardless of updated timestamps. If omitted, only Salesforce records which are newer than linked Drupal records will be pulled.',
    ],
    'examples' => [
      'drush sfpq user' => 'Query and queue all records for "user" Salesforce mapping.',
      'drush sfpq user --where="Email like \'%foo%\' AND (LastName = \'bar\' OR FirstName = \'bar\')"' => 'Query and queue all records for "user" Salesforce mapping with Email field containing the string "foo" and First or Last name equal to "bar"',
      'drush sfpq' => 'Fetch and process all pull queue items',
      'drush sfpq --start="-25 minutes" --stop="-5 minutes"' => 'Fetch updated records for all mappings between 25 minutes and 5 minutes old, and process them.',
      'drush sfpq foo --start="-25 minutes" --stop="-5 minutes"' => 'Fetch updated records for mapping "foo" between 25 minutes and 5 minutes old, and process them.',
    ],
  ];

  $items['sf-pull-file'] = [
    'category' => 'salesforce',
    'description' => 'Given a mapping, enqueue a list of object IDs to be pulled from a CSV file, e.g. a Salesforce report. The first column of the CSV file must be SFIDs. Additional columns will be ignored.',
    'aliases' => ['sfpf', 'sfif'],
    'arguments' => [
      'file' => 'CSV file name of 15- or 18-character Salesforce ids to be pulled. ',
      'name' => 'Machine name of the Salesforce Mapping for which to queue pull records.',
    ],
  ];

  $items['sf-pull-reset'] = [
    'category' => 'salesforce',
    'description' => 'Reset pull timestamps for one or all Salesforce Mappings, and set all mapped objects to be force-pulled.',
    'arguments' => [
      'name' => [
        'description' => 'Machine name of the Salesforce Mapping for which to reset pull timestamps.',
      ],
    ],
    'options' => [
      'delete' => 'Reset delete date timestamp (instead of pull date timestamp)',
    ],
    'examples' => [
      'drush sf-pull-reset' => 'Reset pull timestamps for all mappings.',
      'drush sf-pull-reset foo' => 'Reset pull timestamps for mapping "foo"',
      'drush sf-pull-reset --delete' => 'Reset "delete" timestamps for all mappings',
      'drush sf-pull-reset foo --delete' => 'Reset "delete" timestamp for mapping "foo"',
    ],
  ];

  $items['sf-pull-set'] = [
    'category' => 'salesforce',
    'description' => 'Set pull timestamp on a single Salesforce Mappings to a specific point in history (or now).',
    'arguments' => [
      'name' => [
        'description' => 'Machine name of the Salesforce Mapping for which to reset pull timestamps.',
      ],
      'time' => [
        'description' => 'Timestamp to set the value to. Defaults to the runtime.',
      ],
    ],
    'examples' => [
      'drush sf-pull-set foo' => 'Set pull timestamps for mapping "foo" to "now"',
      'drush sf-pull-set foo 1517416761' => 'Set pull timestamps for mapping "foo" to Jan 31, 2018, around 8:40am time in Portland, OR',
    ],
  ];

  return $items;
}

/**
 * Queues records for pull from salesforce for the given mapping.
 *
 * @param string $name
 *   Mapping name.
 */
function drush_salesforce_pull_sf_pull_query($name) {
  _drush_salesforce_deprecated();
  if (!($mapping = _salesforce_drush_get_mapping($name))) {
    return;
  }

  if ($start = drush_get_option('start')) {
    $start = strtotime($start);
  }
  else {
    $start = 0;
  }

  if ($stop = drush_get_option('stop')) {
    $stop = strtotime($stop);
  }
  else {
    $stop = 0;
  }

  $where = drush_get_option('where');

  if (!($soql = $mapping->getPullQuery([], $start, $stop))) {
    drush_log(dt('!mapping: Unable to generate pull query. Does this mapping have any Salesforce Action Triggers enabled?', ['!mapping' => $mapping->id()]), 'error');
    return;
  }

  if ($where) {
    $soql->conditions[] = [$where];
  }

  \Drupal::service('event_dispatcher')->dispatch(
    SalesforceEvents::PULL_QUERY,
    new SalesforceQueryEvent($mapping, $soql)
  );

  drush_log(dt('!mapping: Issuing pull query: !query', ['!query' => (string) $soql, '!mapping' => $mapping->id()]), 'notice');
  $results = \Drupal::service('salesforce.client')->query($soql);

  if (empty($results)) {
    drush_log(dt('!mapping: No records found to pull.', ['!mapping' => $mapping->id()]), 'warning');
    return;
  }

  $force_pull = drush_get_option('force-pull') ? TRUE : FALSE;

  \Drupal::service('salesforce_pull.queue_handler')
    ->enqueueAllResults($mapping, $results, $force_pull);
  drush_log(dt('!mapping: Queued !count items for pull.', ['!count' => $results->size(), '!mapping' => $mapping->id()]), 'success');

}

/**
 * Queues records for pull from Salesforce from the given file and mapping.
 *
 * @param string $file
 *   File name with IDs.
 * @param string $name
 *   Mapping name.
 */
function drush_salesforce_pull_sf_pull_file($file, $name = NULL) {
  _drush_salesforce_deprecated();
  if (empty($file)) {
    drush_log("File argument is required.", 'error');
    drush_log("usage:\n  drush sf-pull-file file_name [mapping_id]", 'error');
    return;
  }
  if (!file_exists($file)) {
    drush_log('File not found.', 'error');
    return;
  }

  if (!($mapping = _salesforce_drush_get_mapping($name))) {
    return;
  }

  // Fetch the base query to make sure we can pull using this mapping.
  $soql = $mapping->getPullQuery([], 1, 0);
  if (empty($soql)) {
    drush_log(dt('Unable to generate pull query for !name. Does this mapping have any Salesforce Action Triggers enabled?'), 'error');
    return;
  }

  $sf = \Drupal::service('salesforce.client');

  $rows = array_map('str_getcsv', file($file));

  // Track IDs to avoid duplicates.
  $seen = [];

  // Max length for SOQL query is 20,000 characters. Chunk the IDs into smaller
  // units to avoid this limit. 1000 IDs per query * 18 chars per ID = up to
  // 18000 characters per query, plus up to 2000 for fields, where condition,
  // etc.
  $queries = [];
  foreach (array_chunk($rows, 1000) as $i => $chunk) {
    // Reset our base query:
    $soql = $mapping->getPullQuery([], 1, 0);

    // Now add all the IDs to it.
    $sfids = [];
    foreach ($chunk as $j => $row) {
      if (empty($row) || empty($row[0])) {
        drush_log(dt('Skipping row !n, no SFID found.', ['!n' => $j]), 'warning');
        continue;
      }
      try {
        $sfid = new SFID($row[0]);
        // Sanity check to make sure the key-prefix is correct.
        // If so, this is probably a good SFID.
        // If not, it is definitely not a good SFID.
        if ($mapping->getSalesforceObjectType() != $sf->getObjectTypeName($sfid)) {
          throw new \Exception();
        }
      }
      catch (\Exception $e) {
        drush_log(dt('Skipping row !n, no SFID found.', ['!n' => $j]), 'warning');
        continue;
      }
      $sfid = (string) $sfid;
      if (empty($sfids[$sfid])) {
        $sfids[] = $sfid;
        $seen[$sfid] = $sfid;
      }
    }
    $soql->addCondition('Id', $sfids, 'IN');
    $queries[] = $soql;
  }
  if (empty($seen)) {
    drush_log(dt('No SFIDs found in the given file.'), 'error');
    return;
  }
  if (!drush_confirm(dt('Ready to enqueue !count records for pull?', ['!count' => count($seen)]))) {
    return;
  }

  foreach ($queries as $soql) {
    \Drupal::service('event_dispatcher')->dispatch(
      SalesforceEvents::PULL_QUERY,
      new SalesforceQueryEvent($mapping, $soql)
    );

    drush_log(dt('Issuing pull query: !query', ['!query' => (string) $soql]));

    $results = \Drupal::service('salesforce.client')->query($soql);

    if (empty($results)) {
      drush_lo('No records found to pull.');
      continue;
    }

    \Drupal::service('salesforce_pull.queue_handler')
      ->enqueueAllResults($mapping, $results);
    drush_print(dt('Queued !count items for pull.', ['!count' => $results->size()]));
  }

}

/**
 * Get an array of all pull mappings, or the given mapping by name.
 *
 * @param string $name
 *   Mapping name.
 *
 * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface[]
 *   Mappings.
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 */
function _salesforce_pull_load_single_mapping_array_or_all_pull_mappings($name = NULL) {
  if ($name != NULL) {
    $mapping = _salesforce_drush_get_mapping($name);
    if ($mapping && !$mapping->doesPull()) {
      return [];
    }
    if ($mapping) {
      return [$mapping];
    }
    return [];
  }
  else {
    return \Drupal::entityTypeManager()
      ->getStorage('salesforce_mapping')
      ->loadPullMappings();
  }
}

/**
 * Reset pull time on all mappings, or the given mapping by name.
 */
function drush_salesforce_pull_sf_pull_reset($name = NULL) {
  $mappings = _salesforce_pull_load_single_mapping_array_or_all_pull_mappings($name);
  if (empty($mappings)) {
    return;
  }
  foreach ($mappings as $mapping) {
    $mapping->setLastPullTime(NULL);
    \Drupal::entityTypeManager()
      ->getStorage('salesforce_mapped_object')
      ->setForcePull($mapping);
  }
}

/**
 * Set pull time on all mappings, or the given mapping by name.
 */
function drush_salesforce_pull_sf_pull_set($name, $time = NULL) {
  _drush_salesforce_deprecated();
  if (is_null($time)) {
    $time = time();
  }
  $mapping = _salesforce_drush_get_mapping($name);
  if ($mapping) {
    $mapping->setLastPullTime($time);
    \Drupal::entityTypeManager()
      ->getStorage('salesforce_mapped_object')
      ->setForcePull($mapping);
  }
}
