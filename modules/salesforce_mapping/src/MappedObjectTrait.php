<?php

namespace Drupal\salesforce_mapping;

/**
 * Provides a trait for various Mapped Object wrapper functions.
 * Assumption is that any class these are attached to has an injected
 * entityTypeManager named etm.
 */
trait MappedObjectTrait {
  /**
   * Returns Salesforce mapped objects for given properties.
   *
   * @param array $properties
   *   An array of properties on the {salesforce_mapping_object} table in the form
   *     'field' => $value.
   *
   * Note, entity_load_multiple_by_properties() does not provide a reset
   * parameter, and neither do we. If clearing the entire salesforce mapping cache
   * is necessary, it should be done explicitly by the caller.
   *
   * @return array
   *   An array of \Drupal\salesforce_mapping\Entity\MappedObject objects,
   *   indexed by id
   *
   * @throws EntityNotFoundException if no mapped objects exist with the given
   *   properties
   */
  function loadMultipleMappedObject($properties = []) {
    $mappings = \Drupal::entityTypeManager()
      ->getStorage('salesforce_mapped_object')
      ->loadByProperties($properties);
    if (empty($mappings)) {
      throw new EntityNotFoundException($properties, 'salesforce_mapped_object');
    }
    return $mappings;
  }

  /**
   * pass-through for loadMultipleMappedObject()
   */
  function loadMappedObjectByDrupal($entity_type, $entity_id) {
    return $this->loadMultipleMappedObject([
      'entity_type_id' => $entity_type,
      'entity_id' => $entity_id,
    ]);
  }

  /**
   * pass-through for loadMultipleMappedObject()
   */
  function loadMappedObjectByEntity($entity, $all = FALSE) {
    return $this->loadMultipleMappedObject([
      'entity_type_id' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
    ]);
  }

  /**
   * pass-through for loadMultipleMappedObject()
   */
  function loadMappedObjectBySfid($salesforce_id) {
    return $this->loadMultipleMappedObject([
      'salesforce_id' => $salesforce_id,
    ]);
  }
}
