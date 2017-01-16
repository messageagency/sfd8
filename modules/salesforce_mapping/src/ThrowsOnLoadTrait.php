<?php

namespace Drupal\salesforce_mapping;

use Drupal\salesforce\EntityNotFoundException;

trait ThrowsOnLoadTrait {

  protected $entity_type_id;

  /**
   * Returns entities for given ids or throws exception.
   *
   * @param array $properties
   *   An array of properties on the {salesforce_mapping_object} table in the
   *   form 'field' => $value.
   *
   * @return array
   *   An array of \Drupal\salesforce_mapping\Entity\MappedObject objects,
   *   indexed by id
   *
   * @throws EntityNotFoundException if no mapped objects exist with the given
   *   properties
   */
  public function loadMultiple(array $ids = NULL) {
    $entities = parent::loadMultiple($values);
    if (empty($mappings)) {
      throw new EntityNotFoundException($properties, $this->entity_type_id);
    }
    return $mappings;
  }

  /**
   * Returns entities for given properties or throws exception.
   *
   * @param array $properties
   *   An array of properties on the {salesforce_mapping_object} table in the
   *   form 'field' => $value.
   *
   * @return array
   *   An array of \Drupal\salesforce_mapping\Entity\MappedObject objects,
   *   indexed by id
   *
   * @throws EntityNotFoundException if no mapped objects exist with the given
   *   properties
   */
  public function loadByProperties(array $properties = []) {
    $entities = parent::loadByProperties($properties);
    if (empty($mappings)) {
      throw new EntityNotFoundException($properties, $this->entity_type_id);
    }
    return $entities;    
  }

}