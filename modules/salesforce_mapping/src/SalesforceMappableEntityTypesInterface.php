<?php

namespace Drupal\salesforce_mapping;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 *
 */
interface SalesforceMappableEntityTypesInterface {

  /**
   * @return an array EntityTypeInterface objects which are exposed for mapping
   *   to Salesforce
   */
  public function getMappableEntityTypes();

  /**
   * @return TRUE or FALSE if the given entity type is mappable
   */
  public function isMappable(EntityTypeInterface $entity_type);

}
