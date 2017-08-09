<?php

namespace Drupal\salesforce_mapping;

use Drupal\Core\Entity\EntityInterface;
use Drupal\salesforce\SObject;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;

/**
 * Defines an interface for salesforce mapping plugins.
 */
interface SalesforceMappingFieldPluginInterface {

  /**
   * Returns label of the tip.
   *
   * @return string
   *   The label of the tip.
   */
  public function label();

  /**
   * Used for returning values by key.
   *
   * @var string
   *   Key of the value.
   *
   * @return string
   *   Value of the key.
   */
  public function get($key);

  /**
   * Used for returning values by key.
   *
   * @var string
   *   Key of the value.
   *
   * @var string
   *   Value of the key.
   */
  public function set($key, $value);

  /**
   * Given a Drupal entity, return the outbound value.
   * @param $entity
   *   The entity being mapped.
   * @param $mapping
   *   The parent SalesforceMapping to which this plugin config belongs.
   */
  public function value(EntityInterface $entity, SalesforceMappingInterface $mapping);

  /**
   * An extension of ::value, ::pushValue does some basic type-checking and
   * validation against Salesforce field types to protect against basic data
   * errors.
   *
   * @param EntityInterface $entity
   * @param SalesforceMappingInterface $mapping
   * @return mixed
   */
  public function pushValue(EntityInterface $entity, SalesforceMappingInterface $mapping);

  /**
   * An extension of ::value, ::pullValue does some basic type-checking and
   * validation against Drupal field types to protect against basic data
   * errors.
   *
   * @param SObject $sf_object
   * @param EntityInterface $entity
   * @param SalesforceMappingInterface $mapping
   * @return mixed
   */
  public function pullValue(SObject $sf_object, EntityInterface $entity, SalesforceMappingInterface $mapping);

  /**
   * Given a SF Mapping, return TRUE or FALSE whether this field plugin can be
   * added via UI. Not used for validation or any other constraints.
   *
   * @param SalesforceMappingInterface $mapping
   *
   * @return bool
   *
   * @see Drupal\salesforce_mapping\Plugin\SalesforceMappingField\Broken
   */
  public static function isAllowed(SalesforceMappingInterface $mapping);

  /**
   * Get/set a key-value config pair for this plugin.
   *
   * @param string $key
   * @param mixed $value
   */
  public function config($key = NULL, $value = NULL);

  /**
   * Whether this plugin supports "push" operations
   *
   * @return bool
   */
  public function push();

  /**
   * Whether this plugin supports "pull" operations
   *
   * @return bool
   */
  public function pull();

  /**
   * Return an array of dependencies, compatible with \Drupal\Component\Plugin\DependentPluginInterface::calculateDependencies
   *
   * @return array
   * @see \Drupal\Component\Plugin\DependentPluginInterface::calculateDependencies
   */
  public function getDependencies(SalesforceMappingInterface $mapping);
}
