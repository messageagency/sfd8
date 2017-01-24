<?php

namespace Drupal\salesforce_mapping;

use Drupal\salesforce\EntityNotFoundException;

/**
 * This trait should be attached to an instance of EntityStorageInterface
 */
trait ThrowsOnLoadTrait {

  protected $throwExceptions = FALSE;

  public function throwExceptions() {
    $this->throwExceptions = TRUE;
    return $this;
  }

  public function supressExceptions() {
    $this->throwExceptions = FALSE;
    return $this;
  }

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
    $mappings = parent::loadMultiple($ids);
    if (empty($mappings)) {
      if ($this->throwExceptions) {
        throw new EntityNotFoundException($ids, $this->getEntityTypeId());
      }
      else {
        return [];
      }
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
    $mappings = parent::loadByProperties($properties);
    if (empty($mappings)) {
      if ($this->throwExceptions) {
        throw new EntityNotFoundException($properties, $this->getEntityTypeId());
      }
      else {
        return [];
      }
    }
    return $mappings;    
  }

}