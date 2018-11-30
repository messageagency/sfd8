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
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being mapped.
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   The parent SalesforceMapping to which this plugin config belongs.
   */
  public function value(EntityInterface $entity, SalesforceMappingInterface $mapping);

  /**
   * Munge the value that's being prepared to push to Salesforce.
   *
   * An extension of ::value, ::pushValue does some basic type-checking and
   * validation against Salesforce field types to protect against basic data
   * errors.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being pushed.
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   The mapping.
   *
   * @return mixed
   *   The value to be pushed to Salesforce.
   */
  public function pushValue(EntityInterface $entity, SalesforceMappingInterface $mapping);

  /**
   * Munge the value from Salesforce that's about to be saved to Drupal.
   *
   * An extension of ::value, ::pullValue does some basic type-checking and
   * validation against Drupal field types to protect against basic data
   * errors.
   *
   * @param \Drupal\salesforce\SObject $sf_object
   *   The SFObject being pulled.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being pulled.
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   The mapping.
   *
   * @return mixed
   *   The value to be pulled to Drupal.
   */
  public function pullValue(SObject $sf_object, EntityInterface $entity, SalesforceMappingInterface $mapping);

  /**
   * Determine whether this plugin is allowed for a given mapping.
   *
   * Given a SF Mapping, return TRUE or FALSE whether this field plugin can be
   * added via UI. Not used for validation or any other constraints. This works
   * like a soft dependency.
   *
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   The mapping.
   *
   * @return bool
   *   TRUE if the field plugin can be added to this mapping.
   *
   * @see \Drupal\salesforce_mapping\Plugin\SalesforceMappingField\Broken
   */
  public static function isAllowed(SalesforceMappingInterface $mapping);

  /**
   * Get/set a key-value config pair for this plugin.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   */
  public function config($key = NULL, $value = NULL);

  /**
   * Whether this plugin supports "push" operations.
   *
   * @return bool
   *   TRUE if this plugin supports push.
   */
  public function push();

  /**
   * Whether this plugin supports "pull" operations.
   *
   * @return bool
   *   TRUE if this plugin supports pull.
   */
  public function pull();

  /**
   * Return an array of dependencies.
   *
   * Compatible with DependentPluginInterface::calculateDependencies().
   *
   * @return array
   *   Depdencies.
   *
   * @see \Drupal\Component\Plugin\DependentPluginInterface::calculateDependencies
   */
  public function getDependencies(SalesforceMappingInterface $mapping);

}
