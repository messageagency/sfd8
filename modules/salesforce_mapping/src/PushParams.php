<?php

namespace Drupal\salesforce_mapping;

use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Wrapper for the array of values which will be pushed to Salesforce.
 * Usable by salesforce.client for push actions: create, upsert, update.
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
   * @param array $params
   *   (optional)
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
      $this->params[$field_plugin->config('salesforce_field')] = $field_plugin->pushValue($entity, $mapping);
    }
  }

  /**
   * @return SalesforceMapping for this PushParams
   */
  public function getMapping() {
    return $this->mapping;
  }

  /**
   * @return EntityInterface for this PushParams
   */
  public function getDrupalEntity() {
    return $this->drupal_entity;
  }

  /**
   * @return array of params
   */
  public function getParams() {
    return $this->params;
  }

  /**
   * @return mixed the given param value for $key
   * @throws Exception
   */
  public function getParam($key) {
    if (!array_key_exists($key, $this->params)) {
      throw new \Exception("Param key $key does not exist");
    }
    return $this->params[$key];
  }

  /**
   * @param $params
   *   array of params to set for thie PushParams
   * @return $this
   */
  public function setParams(array $params) {
    $this->params = $params;
    return $this;
  }

  /**
   * @param string $key
   *   Key to set for this param
   * @param mixed $value
   *   Value to set for this param.
   * @return $this
   */
  public function setParam($key, $value) {
    $this->params[$key] = $value;
    return $this;
  }

  /**
   * @param string $key
   *   Key to unset for this param
   * @return $this
   */
  public function unsetParam($key) {
    unset($this->params[$key]);
    return $this;
  }

}
