<?php

namespace Drupal\salesforce_mapping\Entity;

/**
 *
 */
interface SalesforceMappingInterface {
  // Placeholder interface.
  // @TODO figure out what to abstract out of SalesforceMapping

  public function __construct(array $values = [], $entity_type);

  public function __get($key);
}
