<?php

namespace Drupal\salesforce_mapping;

use Symfony\Component\EventDispatcher\Event;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Wrapper for the array of values which will be pushed to Salesforce.
 * Usable by salesforce.client for push actions: create, upsert, update
 */
class PushParams {

  protected $params;
  protected $mapping;
  protected $drupal_entity;

  /**
   * Given a Drupal entity, return an array of Salesforce key-value pairs
   * previously salesforce_push_map_params (d7)
   *
   * @param SalesforceMappingInterface $mapping
   * @param EntityInterface $entity
   * @param array $params (optional)
   */
  public function __construct(SalesforceMappingInterface $mapping, EntityInterface $entity, array $params = []) {
    $this->mapping = $mapping;
    $this->drupal_entity = $entity;
    $this->params = $params;
    foreach ($mapping->getFieldMappings() as $field_plugin) {
      // Skip fields that aren't being pushed to Salesforce.
      if (!$field_plugin->push()) {
        continue;
      }
      $this->params[$field_plugin->config('salesforce_field')] =  $field_plugin->value($entity);
    }
  }

  public function getMapping() {
    return $this->mapping;
  }

  public function getDrupalEntity() {
    return $this->drupal_entity;
  }

  public function getParams() {
    return $this->params;
  }

  /**
   * @throws Exception
   */
  public function getParam($key) {
    if (!array_key_exists($key, $this->params)) {
      throw new Exception("Param key $key does not exist");
    }
    return $this->params[$key];
  }

  public function setParams(array $params) {
    $this->params = $params;
    return $this;
  }

  public function setParam($key, $value) {
    $this->params[$key] = $value;
    return $this;
  }

}
