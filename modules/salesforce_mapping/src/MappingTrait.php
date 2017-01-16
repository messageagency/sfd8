<?php

namespace Drupal\salesforce_mapping;

/**
 * Provides a trait for various Mapping object wrapper functions.
 * Assumption is that any class these are attached to has an injected
 * entityTypeManager named etm.
 */
trait MappingTrait {
  /**
   * Loads a single salesforce_mapping or all of them if no name provided.
   *
   * @param string $name
   *   Name of the map to load, or NULL to load all
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMapping
   *   The requested mapping or an array of all mappings, indexed by id, if $name
   *   was not specified
   *
   * @throws EntityNotFoundException if no mapping exists with the given name
   */
  public function loadMapping($name) {
    $mapping = $this->$etm->getStorage('salesforce_mapping')->load($name);
    if (empty($mapping)) {
     throw new EntityNotFoundException($name, 'salesforce_mapping');
    }
    return $mapping;
  }

  /**
   * Loads multiple salesforce_mappings based on a set of matching conditions.
   *
   * @param array $properties (optional)
   *   An array of properties on the \Drupal\salesforce_mapping\Entity\SalesforceMapping in the form
   *     'field' => $value.
   *   If $properties is empty, return an array of all mappings.
   *
   * @return array
   *   An array of \Drupal\salesforce_mapping\Entity\SalesforceMapping objects, indexed by id
   *
   * @throws Exception if not mappings exist with the given properties
   */
  public function loadMultipleMapping($properties = []) {
    $mappings = [];
    $mappings = $this->etm->getStorage('salesforce_mapping')->loadByProperties($properties);
    if (empty($mappings)) {
      $bt = debug_backtrace(FALSE);
      foreach ($bt as &$e) {
        unset($e['args']);
      }
      throw new EntityNotFoundException($properties, 'salesforce_mapping');
    }
    return $mappings;
  }

  /**
   * pass-through for loadMultipleMapping()
   */
  public function loadMappingByDrupal($entity_type) {
    return $this->loadMultipleMappings(["drupal_entity_type" => $entity_type]);
  }

  /**
   * Return a unique list of mapped Salesforce object types.
   * @see loadMultipleMapping()
   * @throws EntityNotFoundException if no mappings have been created yet.
   */
  function getMappedSobjectTypes() {
    $object_types = [];
    $mappings = $this->loadMultipleMappings();
    foreach ($mappings as $mapping) {
      $type = $mapping->getSalesforceObjectType();
      $object_types[$type] = $type;
    }
    return $object_types;
  }
}
