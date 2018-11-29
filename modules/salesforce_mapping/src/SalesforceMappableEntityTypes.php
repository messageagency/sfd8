<?php

namespace Drupal\salesforce_mapping;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Mappable entity types constructor.
 */
class SalesforceMappableEntityTypes implements SalesforceMappableEntityTypesInterface {

  /**
   * Constructs a new SalesforceMappableEntities object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_manager) {
    $this->entityTypeManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getMappableEntityTypes() {
    $entity_info = $this->entityTypeManager->getDefinitions();
    $mappable = [];

    // We're only concerned with fieldable entities. This is a relatively
    // arbitrary restriction, but otherwise there would be an unweildy number
    // of entities. Also exclude MappedObjects themselves.
    foreach ($entity_info as $entity_type_id => $entity_type) {
      if ($this->isMappable($entity_type)) {
        $mappable[$entity_type_id] = $entity_type;
      }
    }
    return $mappable;
  }

  /**
   * {@inheritdoc}
   */
  public function isMappable(EntityTypeInterface $entity_type) {
    if (in_array('Drupal\Core\Entity\ContentEntityTypeInterface', class_implements($entity_type))
    && $entity_type->id() != 'salesforce_mapped_object') {
      return TRUE;
    }
    return FALSE;
  }

}
