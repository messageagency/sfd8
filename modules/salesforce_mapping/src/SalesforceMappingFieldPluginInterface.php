<?php

namespace Drupal\salesforce_mapping;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DependentPluginInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\salesforce\SObject;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;

/**
 * Defines an interface for salesforce mapping plugins.
 */
interface SalesforceMappingFieldPluginInterface extends PluginFormInterface, DependentPluginInterface, ConfigurableInterface, ContainerFactoryPluginInterface {

  /**
   * Returns label of the mapping field plugin.
   *
   * @return string
   *   The label of the mapping field plugin.
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
   * Pull callback for field plugins.
   *
   * This callback is overloaded to serve 2 different use cases.
   * - Use case 1: primitive values
   *   If pullValue() returns a primitive value, callers will attempt to set
   *   the value directly on the parent entity.
   * - Use case 2: typed data
   *   If pullValue() returns a TypedDataInterface, callers will assume the
   *   implementation has set the appropriate value(s). The returned TypedData
   *   will be issued to a SalesforceEvents::PULL_ENTITY_VALUE event, but will
   *   otherwise be ignored.
   *
   * @param \Drupal\salesforce\SObject $sf_object
   *   The SFObject being pulled.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being pulled.
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   The mapping.
   *
   * @return \Drupal\Core\TypedData\TypedDataInterface|mixed
   *   If a TypedDataInterface is returned, validate constraints and use
   *   TypedDataManager to set the value on the root entity. Otherwise, set the
   *   value directly via FieldableEntityInterface::set
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
   * On dependency removal, determine if this plugin needs to be removed.
   *
   * @param array $dependencies
   *   Dependencies, as provided to ConfigEntityInterface::onDependencyRemoval.
   *
   * @return bool
   *   TRUE if the field should be removed, otherwise false.
   */
  public function checkFieldMappingDependency(array $dependencies);

}
