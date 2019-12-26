<?php

namespace Drupal\salesforce_mapping;

use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Wrapper for the array of values which will be pushed to Salesforce.
 *
 * Usable by salesforce.client for push actions: create, upsert, update.
 */
class PushParams {

  /**
   * Key-value array of raw data.
   *
   * @var array
   */
  protected $params;

  /**
   * Mapping for this push params.
   *
   * @var \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface
   */
  protected $mapping;

  /**
   * The Drupal entity being parameterized.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $drupalEntity;

  /**
   * Given a Drupal entity, return an array of Salesforce key-value pairs.
   *
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   Salesforce Mapping.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Drupal entity.
   * @param array $params
   *   Initial params values (optional).
   */
  public function __construct(SalesforceMappingInterface $mapping, EntityInterface $entity, array $params = []) {
    $this->mapping = $mapping;
    $this->drupalEntity = $entity;
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
   * Getter.
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface
   *   Mapping.
   */
  public function getMapping() {
    return $this->mapping;
  }

  /**
   * Getter.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   Drupal entity.
   */
  public function getDrupalEntity() {
    return $this->drupalEntity;
  }

  /**
   * Get the raw push data.
   *
   * @return array
   *   The push data.
   */
  public function getParams() {
    return $this->params;
  }

  /**
   * Get a param value for a given key.
   *
   * @param string $key
   *   A param key.
   *
   * @return mixed|null
   *   The given param value for $key, or NULL if $key is not set.
   *
   * @see hasParam()
   */
  public function getParam($key) {
    return static::hasParam($key) ? $this->params[$key] : NULL;
  }

  /**
   * Return TRUE if the given $key is set.
   *
   * @param string $key
   *   A key.
   *
   * @return bool
   *   TRUE if $key is set.
   */
  public function hasParam($key) {
    return array_key_exists($key, $this->params);
  }

  /**
   * Overwrite params wholesale.
   *
   * @param array $params
   *   Array of params to set for thie PushParams.
   *
   * @return $this
   */
  public function setParams(array $params) {
    $this->params = $params;
    return $this;
  }

  /**
   * Set a param.
   *
   * @param string $key
   *   Key to set for this param.
   * @param mixed $value
   *   Value to set for this param.
   *
   * @return $this
   */
  public function setParam($key, $value) {
    $this->params[$key] = $value;
    return $this;
  }

  /**
   * Unset a param value.
   *
   * @param string $key
   *   Key to unset for this param.
   *
   * @return $this
   */
  public function unsetParam($key) {
    unset($this->params[$key]);
    return $this;
  }

}
