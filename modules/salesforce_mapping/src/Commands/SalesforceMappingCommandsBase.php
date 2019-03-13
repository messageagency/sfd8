<?php

namespace Drupal\salesforce_mapping\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\salesforce\Rest\RestClient;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;
use Drupal\salesforce\Commands\SalesforceCommandsBase;
use Drupal\salesforce\Commands\QueryResult;
use Drupal\salesforce\Commands\QueryResultTableFormatter;
use Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface;
use Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface;

/**
 * Shared command base for Salesforce Drush commands.
 */
abstract class SalesforceMappingCommandsBase extends SalesforceCommandsBase {

  /**
   * Salesforce Mapping storage handler.
   *
   * @var \Drupal\salesforce_mapping\SalesforceMappingStorage
   */
  protected $mappingStorage;

  /**
   * Mapped Object storage handler.
   *
   * @var \Drupal\salesforce_mapping\MappedObjectStorage
   */
  protected $mappedObjectStorage;

  /**
   * Salesforce Auth Provider plugin manager service.
   *
   * @var \Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface
   */
  protected $authMan;

  /**
   * Salesforce Auth Token Storage service.
   *
   * @var \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface
   */
  protected $tokenStorage;

  /**
   * SalesforceMappingCommandsBase constructor.
   *
   * @param \Drupal\salesforce\Rest\RestClient $client
   *   SF client.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   Entity type manager.
   * @param \Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface $auth_man
   *   Auth plugin manager.
   * @param \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface $token_storage
   *   Token storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(RestClient $client, EntityTypeManagerInterface $etm, SalesforceAuthProviderPluginManagerInterface $auth_man, SalesforceAuthTokenStorageInterface $token_storage) {
    parent::__construct($client, $etm, $auth_man, $token_storage);

    $this->mappingStorage = $etm->getStorage('salesforce_mapping');
    $this->mappedObjectStorage = $etm->getStorage('salesforce_mapped_object');
  }

  /**
   * Collect a salesforce mapping interactively.
   */
  protected function interactMapping(Input $input, Output $output, $message = 'Choose a Salesforce mapping', $allOption = FALSE, $dir = NULL) {
    if ($name = $input->getArgument('name')) {
      if (strtoupper($name) == 'ALL') {
        $input->setArgument('name', 'ALL');
        return;
      }
      /** @var \Drupal\salesforce_mapping\Entity\SalesforceMapping $mapping */
      $mapping = $this->mappingStorage->load($name);
      if (!$mapping) {
        $this->logger()->error(dt('Mapping %name does not exist.', ['%name' => $name]));
      }
      elseif ($dir == 'push' && !$mapping->doesPush()) {
        $this->logger()->error(dt('Mapping %name does not push.', ['%name' => $name]));
      }
      elseif ($dir == 'pull' && !$mapping->doesPull()) {
        $this->logger()->error(dt('Mapping %name does not push.', ['%name' => $name]));
      }
      else {
        return;
      }
    }
    if ($dir == 'pull') {
      $options = $this->mappingStorage->loadPullMappings();
    }
    elseif ($dir == 'push') {
      $options = $this->mappingStorage->loadPushMappings();
    }
    else {
      $options = $this->mappingStorage->loadMultiple();
    }
    $this->doMappingNameOptions($input, array_keys($options), $message, $allOption);

  }

  /**
   * Collect a salesforce mapping name, and set it to a "name" argument.
   */
  protected function interactPushMappings(Input $input, Output $output, $message = 'Choose a Salesforce mapping', $allOption = FALSE) {
    return $this->interactMapping($input, $output, $message, $allOption, 'push');
  }

  /**
   * Collect a salesforce mapping name, and set it to a "name" argument.
   */
  protected function interactPullMappings(Input $input, Output $output, $message = 'Choose a Salesforce mapping', $allOption = FALSE) {
    return $this->interactMapping($input, $output, $message, $allOption, 'pull');
  }

  /**
   * Helper method to collect the choice from user, given a set of options.
   */
  protected function doMappingNameOptions(Input $input, array $options, $message, $allOption = FALSE) {
    $options = array_combine($options, $options);
    if ($allOption) {
      $options['ALL'] = $allOption;
    }
    if (!$answer = $this->io()->choice($message, $options)) {
      throw new UserAbortException();
    }
    $input->setArgument('name', $answer);
  }

  /**
   * Given a mapping name (and optional direction), get an array of mappings.
   *
   * @param string $name
   *   'ALL' to load all mappings, or a mapping id.
   * @param string $dir
   *   'push'|'pull'|NULL to load limit mappings by push or pull types.
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface[]
   *   The mappings.
   *
   * @throws \Exception
   */
  protected function getMappingsFromName($name, $dir = NULL) {
    if ($name == 'ALL') {
      if ($dir == 'pull') {
        $mappings = $this->mappingStorage->loadPullMappings();
      }
      elseif ($dir == 'push') {
        $mappings = $this->mappingStorage->loadPushMappings();
      }
      else {
        $mappings = $this->mappingStorage->loadMultiple();
      }
    }
    else {
      $mapping = $this->mappingStorage->load($name);
      if ($dir == 'push' && !$mapping->doesPush()) {
        throw new \Exception(dt("Mapping !name does not push.", ['!name' => $name]));
      }
      elseif ($dir == 'pull' && !$mapping->doesPull()) {
        throw new \Exception(dt("Mapping !name does not pull.", ['!name' => $name]));
      }
      $mappings = [$mapping];
    }
    $mappings = array_filter($mappings);
    if (empty($mappings)) {
      if ($dir == 'push') {
        throw new \Exception(dt('No push mappings loaded'));
      }
      if ($dir == 'pull') {
        throw new \Exception(dt('No pull mappings loaded'));
      }
    }
    return $mappings;
  }

  /**
   * Given a mapping name, get an array of matching push mappings.
   *
   * @param string $name
   *   The mapping name.
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface[]
   *   The matching mappings.
   *
   * @throws \Exception
   */
  protected function getPushMappingsFromName($name) {
    return $this->getMappingsFromName($name, 'push');
  }

  /**
   * Given a mappin gname, get an array of matching pull mappings.
   *
   * @param string $name
   *   The mapping name.
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface[]
   *   The pull mappings.
   *
   * @throws \Exception
   */
  protected function getPullMappingsFromName($name) {
    return $this->getMappingsFromName($name, 'pull');
  }

  /**
   * Pass-through helper to add appropriate formatters for a query result.
   *
   * @param \Drupal\salesforce\Commands\QueryResult $query
   *   The query result.
   *
   * @return \Drupal\salesforce\Commands\QueryResult
   *   The same, unchanged query result.
   */
  protected function returnQueryResult(QueryResult $query) {
    $formatter = new QueryResultTableFormatter();
    $formatterManager = Drush::getContainer()->get('formatterManager');
    $formatterManager->addFormatter('table', $formatter);
    return $query;
  }

}
