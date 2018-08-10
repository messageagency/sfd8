<?php

namespace Drupal\salesforce\Commands;

use Drupal\Core\Entity\EntityTypeManager;
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
  /** @var \Drupal\Core\Entity\EntityTypeManager */
  protected $etm;
  /** @var \Drupal\salesforce_mapping\SalesforceMappingStorage */
  protected $mappingStorage;
  /** @var \Drupal\salesforce_mapping\MappedObjectStorage */
  protected $mappedObjectStorage;

  public function __construct(RestClient $client, EntityTypeManager $etm) {
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

  /**
   * Collect a salesforce mapping name, and set it to a "name" argument.
   */
  protected function interactPullMappings(Input $input, Output $output, $message = 'Choose a Salesforce mapping', $allOption = FALSE) {
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
      elseif (!$mapping->doesPull()) {
        $this->logger()->error(dt('Mapping %name does not pull.', ['%name' => $name]));
      }
      else {
        return;
      }
    }
    $options = $this->mappingStorage->loadPullMappings();
    $options = array_combine(array_keys($options), array_keys($options));
    if ($allOption) {
      $options['ALL'] = $allOption;
    }
    if (!$answer = $this->io()->choice($message, $options)) {
      throw new UserAbortException();
    }
    $input->setArgument('name', $answer);
  }

  /**
   * @param string $name
   *
   * @return SalesforceMapping[]
   * @throws \Exception
   */
  protected function getPullMappingsFromName($name) {
    $mappings = [];
    if ($name == 'ALL') {
      $mappings = $this->mappingStorage->loadPullMappings();
    }
    else {
      $mapping = $this->mappingStorage->load($name);
      if (!$mapping->doesPull()) {
        throw new \Exception(dt("Mapping !name does not pull.", ['!name' => $name]));
      }
      $mappings = [$mapping];
    }
    $mappings = array_filter($mappings);
    if (empty($mappings)) {
      throw new \Exception(dt('No pull mappings loaded'));
    }
    return $mappings;
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