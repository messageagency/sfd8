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
use Drupal\salesforce_mapping\Entity\MappedObject;
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
      throw new Exception('Salesforce needs to be authorized to connect to this website.');
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

  public function setMapping(SalesforceMapping $mapping) {
    $this->mapping = $mapping;
    return $this;
  }

  public function getMapping() {
    return $this->mapping;
  }

  public function setEntity(EntityInterface $entity) {
    $this->entity = $entity;
    return $this;
  }

  public function getEntity() {
    return $this->entity;
  }

}