<?php

namespace Drupal\salesforce_mapping;

use Drupal\Core\Entity\EntityInterface;
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
   * Given a SF Mapping, return TRUE or FALSE whether this field plugin can be
   * added.
   *
   * @param SalesforceMappingInterface $mapping
   *
   * @return bool
   */
  public static function isAllowed(SalesforceMappingInterface $mapping);

}
