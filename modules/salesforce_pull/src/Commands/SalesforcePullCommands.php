<?php

namespace Drupal\salesforce_pull\Commands;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\salesforce_mapping\Commands\SalesforceMappingCommandsBase;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce\SFID;
use Drupal\salesforce_mapping\Event\SalesforceQueryEvent;
use Drupal\salesforce_pull\QueueHandler;
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
class SalesforcePullCommands extends SalesforceMappingCommandsBase {

  /**
   * Pull queue handler service.
   *
   * @var \Drupal\salesforce_pull\QueueHandler
   */
  protected $pullQueue;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcher
   */
  protected $eventDispatcher;

  /**
   * SalesforcePullCommands constructor.
   *
   * @param \Drupal\salesforce\Rest\RestClient $client
   *   Salesforce client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   Entity type manager.
   * @param \Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface $auth_man
   *   Auth plugin manager.
   * @param \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface $token_storage
   *   Token storage.
   * @param \Drupal\salesforce_pull\QueueHandler $pullQueue
   *   Pull queue handler service.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $eventDispatcher
   *   Event dispatcher service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(RestClient $client, EntityTypeManagerInterface $etm, SalesforceAuthProviderPluginManagerInterface $auth_man, SalesforceAuthTokenStorageInterface $token_storage, QueueHandler $pullQueue, ContainerAwareEventDispatcher $eventDispatcher) {
    parent::__construct($client, $etm, $auth_man, $token_storage);
    $this->pullQueue = $pullQueue;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Fetch a pull mapping interactively.
   *
   * @hook interact salesforce_pull:pull-query
   */
  public function interactPullQuery(Input $input, Output $output) {
    return $this->interactPullMappings($input, $output, $message = 'Choose a Salesforce mapping', 'Pull All');
  }

  /**
   * Fetch a filename interactively.
   *
   * @hook interact salesforce_pull:pull-file
   */
  public function interactPullFile(Input $input, Output $output) {
    $file = $input->getArgument('file');
    if (empty($file)) {
      return;
    }
    if (!file_exists($file)) {
      $this->logger()->error('File does not exist');
      return;
    }

    return $this->interactPullMappings($input, $output);
  }

  /**
   * Fetch a pull mapping interactively.
   *
   * @hook interact salesforce_pull:pull-reset
   */
  public function interactPullReset(Input $input, Output $output) {
    return $this->interactPullMappings($input, $output, $message = 'Choose a Salesforce mapping', 'Reset All');
  }

  /**
   * Fetch a pull mapping interactively.
   *
   * @hook interact salesforce_pull:pull-set
   */
  public function interactPullSet(Input $input, Output $output) {
    return $this->interactPullMappings($input, $output, $message = 'Choose a Salesforce mapping', 'Set All');
  }

  /**
   * Given a mapping, enqueue records for pull from Salesforce.
   *
   * Ignoring modification timestamp. This command is useful, for example, when
   * seeding content for a Drupal site prior to deployment.
   *
   * @param string $name
   *   Machine name of the Salesforce Mapping for which to queue pull records.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @throws \Exception
   *
   * @option where
   *   A WHERE clause to add to the SOQL pull query. Default behavior is to
   *   query and pull all records.
   * @option start
   *   strtotime()able string for the start timeframe over which to pull, e.g.
   *   "-5 hours". If omitted, use the value given by the mapping's pull
   *   timestamp. Must be in the past.
   * @option stop
   *   strtotime()able string for the end timeframe over which to pull, e.g.
   *   "-5 hours". If omitted, defaults to "now". Must be "now" or earlier.
   * @option force-pull
   *   if given, force all queried records to be pulled regardless of updated
   *   timestamps. If omitted, only Salesforce records which are newer than
   *   linked Drupal records will be pulled.
   * @usage drush sfpq user
   *   Query and queue all records for "user" Salesforce mapping.
   * @usage drush sfpq user --where="Email like '%foo%' AND (LastName = 'bar'
   *   OR FirstName = 'bar')"
   *   Query and queue all records for "user" Salesforce mapping with Email
   *   field containing the string "foo" and First or Last name equal to "bar"
   * @usage drush sfpq
   *   Fetch and process all pull queue items
   * @usage drush sfpq --start="-25 minutes" --stop="-5 minutes"
   *   Fetch updated records for all mappings between 25 minutes and 5 minutes
   *   old, and process them.
   * @usage drush sfpq foo --start="-25 minutes" --stop="-5 minutes"
   *   Fetch updated records for mapping "foo" between 25 minutes and 5 minutes
   *   old, and process them.
   *
   * @command salesforce_pull:pull-query
   * @aliases sfpq,sfiq,sf-pull-query,salesforce_pull:query
   */
  public function pullQuery($name, array $options = [
    'where' => '',
    'start' => 0,
    'stop' => 0,
    'force-pull' => FALSE,
  ]) {
    $mappings = $this->getPullMappingsFromName($name);
    $start = $options['start'] ? strtotime($options['start']) : 0;
    $stop = $options['stop'] ? strtotime($options['stop']) : 0;
    if ($start > $stop) {
      $this->logger()->error(dt('Stop date-time must be later than start date-time.'));
      return;
    }

    foreach ($mappings as $mapping) {
      if (!($soql = $mapping->getPullQuery([], $start, $stop))) {
        $this->logger()->error(dt('!mapping: Unable to generate pull query. Does this mapping have any Salesforce Action Triggers enabled?', ['!mapping' => $mapping->id()]));
        continue;
      }

      if ($options['where']) {
        $soql->conditions[] = [$options['where']];
      }

      $this->eventDispatcher->dispatch(
        SalesforceEvents::PULL_QUERY,
        new SalesforceQueryEvent($mapping, $soql)
      );

      $this->logger()->info(dt('!mapping: Issuing pull query: !query', [
        '!query' => (string) $soql,
        '!mapping' => $mapping->id(),
      ]));
      $results = $this->client->query($soql);

      if (empty($results)) {
        $this->logger()->warning(dt('!mapping: No records found to pull.', ['!mapping' => $mapping->id()]));
        return;
      }

      $this->pullQueue->enqueueAllResults($mapping, $results, $options['force-pull']);

      $this->logger()->info(dt('!mapping: Queued !count items for pull.', [
        '!count' => $results->size(),
        '!mapping' => $mapping->id(),
      ]));
    }
  }

  /**
   * Given a mapping, enqueue a list of object IDs to be pulled from CSV file.
   *
   * E.g. a Salesforce report. The first column of the CSV file must be SFIDs.
   * Additional columns will be ignored.
   *
   * @param string $file
   *   CSV file name of 15- or 18-character Salesforce ids to be pulled.
   * @param string $name
   *   Machine name of the Salesforce Mapping for which to queue pull records.
   *
   * @command salesforce_pull:pull-file
   * @aliases sfpf,sfif,sf-pull-file,salesforce_pull:file
   */
  public function pullFile($file, $name) {
    /** @var \Drupal\salesforce_mapping\Entity\SalesforceMapping $mapping */
    if (!($mapping = $this->mappingStorage->load($name))) {
      $this->logger()->error(dt('Failed to load mapping "%name"', ['%name' => $name]));
      return;
    }

    // Fetch the base query to make sure we can pull using this mapping.
    $soql = $mapping->getPullQuery([], 1, 0);
    if (empty($soql)) {
      $this->logger()->error(dt('Failed to load mapping "%name"', ['%name' => $name]));
      return;
    }

    $rows = array_map('str_getcsv', file($file));

    // Track IDs to avoid duplicates.
    $seen = [];

    // Max length for SOQL query is 20,000 characters. Chunk the IDs into
    // smaller units to avoid this limit. 1000 IDs per query * 18 chars per ID,
    // up to 18000 characters per query, plus up to 2000 for fields, where
    // condition, etc.
    $queries = [];
    foreach (array_chunk($rows, 1000) as $i => $chunk) {
      // Reset our base query:
      $soql = $mapping->getPullQuery([], 1, 0);

      // Now add all the IDs to it.
      $sfids = [];
      foreach ($chunk as $j => $row) {
        if (empty($row) || empty($row[0])) {
          $this->logger->warning(dt('Skipping row !n, no SFID found.', ['!n' => $j]));
          continue;
        }
        try {
          $sfid = new SFID($row[0]);
          // Sanity check to make sure the key-prefix is correct.
          // If so, this is probably a good SFID.
          // If not, it is definitely not a good SFID.
          if ($mapping->getSalesforceObjectType() != $this->client->getObjectTypeName($sfid)) {
            $this->logger()->error(dt('SFID !sfid does not match type !type', ['!sfid' => (string) $sfid, '!type' => $mapping->getSalesforceObjectType()]));
            continue;
          }
        }
        catch (\Exception $e) {
          $this->logger->warning(dt('Skipping row !n, no SFID found.', ['!n' => $j]));
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
      $this->logger()->error('No SFIDs found in the given file.');
      return;
    }

    if (!$this->io()->confirm(dt('Ready to enqueue !count records for pull?', ['!count' => count($seen)]))) {
      return;
    }

    foreach ($queries as $soql) {
      $this->eventDispatcher->dispatch(
        SalesforceEvents::PULL_QUERY,
        new SalesforceQueryEvent($mapping, $soql)
      );

      $this->logger()->info(dt('Issuing pull query: !query', ['!query' => (string) $soql]));

      $results = $this->client->query($soql);

      if (empty($results)) {
        $this->logger()->warning('No records found to pull.');
        continue;
      }

      $this->pullQueue->enqueueAllResults($mapping, $results);
      $this->logger()->info(dt('Queued !count items for pull.', ['!count' => $results->size()]));
    }
  }

  /**
   * Reset pull timestamps for one or all Salesforce Mappings.
   *
   * @param string $name
   *   Mapping id.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @option delete
   *   Reset delete date timestamp (instead of pull date timestamp)
   * @usage drush sf-pull-reset
   *   Reset pull timestamps for all mappings.
   * @usage drush sf-pull-reset foo
   *   Reset pull timestamps for mapping "foo"
   * @usage drush sf-pull-reset --delete
   *   Reset "delete" timestamps for all mappings
   * @usage drush sf-pull-reset foo --delete
   *   Reset "delete" timestamp for mapping "foo"
   *
   * @command salesforce_pull:pull-reset
   * @aliases sf-pull-reset,salesforce_pull:reset
   */
  public function pullReset($name, array $options = ['delete' => NULL]) {
    $mappings = $this->getPullMappingsFromName($name);
    foreach ($mappings as $mapping) {
      if ($options['delete']) {
        $mapping->setLastDeleteTime(NULL);
      }
      else {
        $mapping->setLastPullTime(NULL);
      }
      \Drupal::entityTypeManager()
        ->getStorage('salesforce_mapped_object')
        ->setForcePull($mapping);
      $this->logger()->info(dt('Pull timestamp reset for !name', ['!name' => $name]));
    }
  }

  /**
   * Set a specific pull timestamp on a single Salesforce Mapping.
   *
   * @param string $name
   *   Mapping id.
   * @param int $time
   *   Timestamp.
   * @param array $options
   *   Assoc array of options.
   *
   * @throws \Exception
   *
   * @option delete
   *   Reset delete date timestamp (instead of pull date timestamp)
   * @usage drush sf-pull-set foo
   *   Set pull timestamps for mapping "foo" to "now"
   * @usage drush sf-pull-set foo 1517416761
   *   Set pull timestamps for mapping "foo" to 2018-01-31T15:39:21+00:00
   *
   * @command salesforce_pull:pull-set
   * @aliases sf-pull-set,salesforce_pull:set
   */
  public function pullSet($name, $time, array $options = ['delete' => NULL]) {
    $mappings = $this->getPullMappingsFromName($name);
    foreach ($mappings as $mapping) {
      $mapping->setLastPullTime(NULL);
      if ($options['delete']) {
        $mapping->setLastDeleteTime($time);
      }
      else {
        $mapping->setLastPullTime($time);
      }
      $this->mappedObjectStorage->setForcePull($mapping);
      $this->logger()->info(dt('Pull timestamp reset for !name', ['!name' => $name]));
    }
  }

}
