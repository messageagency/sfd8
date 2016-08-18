<?php

/**
 * @file
 * Contains \Drupal\salesforce_push\SalesforcePushPluginInterface.
 */

namespace Drupal\salesforce_push;

use Drupal\Core\Entity\EntityInterface;
use Drupal\salesforce\SalesforceClient;
use Drupal\Core\Entity\EntityManagerInterface;
use Salesforce\salesforce_mapping\Entity\MappedObject;
use Symfony\Component\DependencyInjection\ContainerInterface;

interface SalesforcePushPluginInterface {

  public function __construct(array $configuration, $plugin_id, array $plugin_definition, SalesforceClient $sf_client);

  public function push();

  public function delete();

}
