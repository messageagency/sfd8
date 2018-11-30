<?php

namespace Drupal\salesforce_mapping;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Mappable entity types interface.
 */
interface SalesforceMappableEntityTypesInterface {

  /**
   * Get an array of entity types that are mappable.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   Objects which are exposed for mapping to Salesforce.
   */
  public function getMappableEntityTypes();

  /**
   * Given an entity type, return true or false to indicate if it's mappable.
   *
   * @return bool
   *   TRUE or FALSE to indicate if the given entity type is mappable.
   */
  public function isMappable(EntityTypeInterface $entity_type);

}
