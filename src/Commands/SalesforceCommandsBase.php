<?php

namespace Drupal\salesforce\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drush\Commands\DrushCommands;
use Drush\Drush;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;

abstract class SalesforceCommandsBase extends DrushCommands {

  /** @var \Drupal\salesforce\Rest\RestClient */
  protected $client;
  /** @var \Drupal\Core\Entity\EntityTypeManagerInterface */
  protected $etm;
  /** @var \Drupal\salesforce_mapping\SalesforceMappingStorage */
  protected $mappingStorage;
  /** @var \Drupal\salesforce_mapping\MappedObjectStorage */
  protected $mappedObjectStorage;

  public function __construct(RestClient $client, EntityTypeManagerInterface $etm) {
    $this->client = $client;
    $this->etm = $etm;
    $this->mappingStorage = $etm->getStorage('salesforce_mapping');
    $this->mappedObjectStorage = $etm->getStorage('salesforce_mapped_object');
  }

  /**
   * Collect a salesforce object name, and set it to "object" argument.
   *
   * NB: there's no actual validation done here against Salesforce objects.
   * If there's a way to attach multiple hooks to one method, please patch this.
   */
  protected function interactObject(Input $input, Output $output, $message = 'Choose a Salesforce object name') {
    if (!$input->getArgument('object')) {
      $objects = $this->client->objects();
      if (!$answer = $this->io()->choice($message, array_combine(array_keys($objects), array_keys($objects)))) {
        throw new UserAbortException();
      }
      $input->setArgument('object', $answer);
    }
  }

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
   * Given a mapping name (and option direction), get an array of mappings
   * @param string $name
   *   'ALL' to load all mappings, or a mapping id.
   * @param string $dir
   *   'push'|'pull'|NULL to load limit mappings by push or pull types.
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface[]
   */
  protected function getMappingsFromName($name, $dir = NULL) {
    $mappings = [];
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
      throw new \Exception(dt('No push mappings loaded'));
    }
    return $mappings;
  }

  /**
   * @param $name
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMapping[]
   * @throws \Exception
   */
  protected function getPushMappingsFromName($name) {
    return $this->getMappingsFromName($name, 'push');
  }

  /**
   * @param string $name
   *
   * @return SalesforceMapping[]
   * @throws \Exception
   */
  protected function getPullMappingsFromName($name) {
    return $this->getMappingsFromName($name, 'pull');
  }

  /**
   * @param \Drupal\salesforce\Commands\QueryResult $query
   *
   * @return \Drupal\salesforce\Commands\QueryResult
   */
  protected function returnQueryResult(QueryResult $query) {
    $formatter = new QueryResultTableFormatter();
    $formatterManager = Drush::getContainer()->get('formatterManager');
    $formatterManager->addFormatter('table', $formatter);
    return $query;
  }

}
