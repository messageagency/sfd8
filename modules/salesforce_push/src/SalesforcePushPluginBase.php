<?php

/**
 * @file
 * Contains \Drupal\salesforce_push\SalesforcePushPluginBase.
 */

namespace Drupal\salesforce_push;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\salesforce\SalesforceClient;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce_mapping\Entity\SalesforceMappedObject;
use Drupal\salesforce_push\SalesforcePushPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class SalesforcePushPluginBase extends PluginBase implements SalesforcePushPluginInterface, ContainerFactoryPluginInterface {

  protected $entity;
  protected $mapping;
  protected $sf_client;
  protected $mapped_object;

  // We'll need some entity manager stuff, not sure what yet though.
  // protected $entity_manager;

  public function __construct(array $configuration, $plugin_id, array $plugin_definition, SalesforceClient $sf_client) {
    $this->sf_client = $sf_client;
    if (!$this->sf_client->isAuthorized()) {
      // Abort early if we can't do anything. Allows frees us from calling
      // isAuthorized() over and over.
      throw new SalesforceException('Salesforce needs to be authorized to connect to this website.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('salesforce.client')
    );
  }

  public function init(EntityInterface $entity, SalesforceMapping $mapping) {
    $this->entity = $entity;
    $this->mapping = $mapping;
    $this->mapped_object = salesforce_mapped_object_load_by_entity($entity);
    return $this;
  }

}