<?php

namespace Drupal\salesforce_mapping;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityInterface;

/**
 * Class MappedObjectStorage.
 *
 * Extends ConfigEntityStorage to add some commonly used convenience wrappers.
 *
 * @package Drupal\salesforce_mapping
 */
class SalesforceMappingStorage extends ConfigEntityStorage {

  /**
   * Pass-through for loadMultipleMapping()
   */
  public function loadByDrupal($entity_type_id) {
    return $this->loadByProperties(["drupal_entity_type" => $entity_type_id]);
  }

  /**
   * Pass-through for loadMultipleMapping() including bundle.
   */
  public function loadByEntity(EntityInterface $entity) {
    return $this->loadByProperties([
      'drupal_entity_type' => $entity->getEntityTypeId(),
      'drupal_bundle' => $entity->bundle(),
    ]);
  }

  /**
   * Return an array of SalesforceMapping entities who are push-enabled.
   *
   * @param string $entity_type_id
   *   The entity type id. If given, filter the mappings by only this type.
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface[]
   *   The Mappings.
   */
  public function loadPushMappings($entity_type_id = NULL) {
    $properties = empty($entity_type_id)
      ? []
      : ["drupal_entity_type" => $entity_type_id];
    return $this->loadPushMappingsByProperties($properties);
  }

  /**
   * Get push Mappings to be processed during cron.
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface[]
   *   The Mappings to process.
   */
  public function loadCronPushMappings() {
    if ($this->configFactory->get('salesforce.settings')->get('standalone')) {
      return [];
    }
    $properties["push_standalone"] = FALSE;
    return $this->loadPushMappingsByProperties($properties);
  }

  /**
   * Get pull Mappings to be processed during cron.
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface[]
   *   The pull Mappings.
   */
  public function loadCronPullMappings() {
    if ($this->configFactory->get('salesforce.settings')->get('standalone')) {
      return [];
    }
    return $this->loadPullMappingsByProperties(["pull_standalone" => FALSE]);
  }

  /**
   * Return an array push-enabled mappings by properties.
   *
   * @param array $properties
   *   Properties array for storage handler.
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface[]
   *   The push mappings.
   *
   * @see ::loadByProperties()
   */
  public function loadPushMappingsByProperties(array $properties) {
    $mappings = $this->loadByProperties($properties);
    foreach ($mappings as $key => $mapping) {
      if (!$mapping->doesPush()) {
        continue;
      }
      $push_mappings[$key] = $mapping;
    }
    if (empty($push_mappings)) {
      return [];
    }
    return $push_mappings;
  }

  /**
   * Return an array push-enabled mappings by properties.
   *
   * @param array $properties
   *   Properties array for storage handler.
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface[]
   *   The pull mappings.
   *
   * @see ::loadByProperties()
   */
  public function loadPullMappingsByProperties(array $properties) {
    $mappings = $this->loadByProperties($properties);
    foreach ($mappings as $key => $mapping) {
      if (!$mapping->doesPull()) {
        continue;
      }
      $push_mappings[$key] = $mapping;
    }
    if (empty($push_mappings)) {
      return [];
    }
    return $push_mappings;
  }

  /**
   * Return an array of SalesforceMapping entities who are pull-enabled.
   *
   * @param string $entity_type_id
   *   Optionally filter by entity type id.
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface[]
   *   The Mappings.
   */
  public function loadPullMappings($entity_type_id = NULL) {
    $pull_mappings = [];
    $properties = empty($entity_type_id)
      ? []
      : ["drupal_entity_type" => $entity_type_id];
    $mappings = $this->loadByProperties($properties);

    foreach ($mappings as $key => $mapping) {
      if (!$mapping->doesPull()) {
        continue;
      }
      $pull_mappings[$key] = $mapping;
    }
    if (empty($pull_mappings)) {
      return [];
    }
    return $pull_mappings;
  }

  /**
   * {@inheritdoc}
   */
  public function loadByProperties(array $values = []) {
    // Build a query to fetch the entity IDs.
    $entity_query = $this->getQuery();
    $this->buildPropertyQuery($entity_query, $values);
    // Sort by the mapping weight to ensure entities/objects are processed in
    // the correct order.
    $entity_query->sort('weight');
    $result = $entity_query->execute();
    return $result ? $this->loadMultiple($result) : [];
  }

  /**
   * Return a unique list of mapped Salesforce object types.
   *
   * @return array
   *   Mapped object types.
   *
   * @see loadMultipleMapping()
   */
  public function getMappedSobjectTypes() {
    $object_types = [];
    $mappings = $this->loadByProperties();
    foreach ($mappings as $mapping) {
      $type = $mapping->getSalesforceObjectType();
      $object_types[$type] = $type;
    }
    return $object_types;
  }

}
