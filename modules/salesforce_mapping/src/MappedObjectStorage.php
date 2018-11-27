<?php

namespace Drupal\salesforce_mapping;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\salesforce\SFID;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Class MappedObjectStorage.
 * Extends ConfigEntityStorage to add some commonly used convenience wrappers.
 *
 * @package Drupal\salesforce_mapping
 */
class MappedObjectStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  // During testing, complaints alternate between the type of
  // cache interface expected between below:
  // CacheBackendInterface.

  /**
   * DatabaseBackendFactory.
   */
  public function __construct($entity_type_id, Connection $database, EntityManagerInterface $entity_manager, CacheBackendInterface $cache, LanguageManagerInterface $language_manager) {
    // @TODO the $entity_type needs to be in the constructor and not
    // devrived from from $entity_type_id. This is because of the parent
    // class SqlContentEntityStorage's createInstance method, which while
    // ultimately calls it's own constructer through here, is calling this
    // constuctor with the same paramter blueprint, which expects
    // EntityTypeInterface and not a string.
    $entity_type = $entity_manager->getDefinition($entity_type_id);
    parent::__construct($entity_type, $database, $entity_manager, $cache, $language_manager);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type->id(),
      $container->get('database'),
      $container->get('entity.manager'),
      $container->get('cache.entity'),
      $container->get('language_manager')
    );
  }

  /**
   * Load MappedObjects by entity type id and entity id.
   *
   * @pararm string entity_type_id
   *
   * @param int/string entity_id
   *
   * @return array
   *
   * @see loadByProperties()
   */
  public function loadByDrupal($entity_type_id, $entity_id) {
    return $this->loadByProperties([
      'drupal_entity__target_type' => $entity_type_id,
      'drupal_entity__target_id' => $entity_id,
    ]);
  }

  /**
   * Load MappedObjects by Drupal Entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return array
   *
   * @see loadByProperties()
   */
  public function loadByEntity(EntityInterface $entity) {
    return $this->loadByProperties([
      'drupal_entity__target_type' => $entity->getEntityTypeId(),
      'drupal_entity__target_id' => $entity->id(),
    ]);
  }

  /**
   * Load a single MappedObject by Drupal Entity and Mapping.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return MappedObjectInterface|null
   *
   * @see loadByProperties()
   */
  public function loadByEntityAndMapping(EntityInterface $entity, SalesforceMappingInterface $mapping) {
    $result = $this->loadByProperties([
      'drupal_entity__target_type' => $entity->getEntityTypeId(),
      'drupal_entity__target_id' => $entity->id(),
      'salesforce_mapping' => $mapping->id()
    ]);
    return empty($result) ? NULL : reset($result);
  }

  /**
   * Load MappedObjects by Salesforce ID.
   *
   * @param \Drupal\salesforce\SFID $salesforce_id
   *
   * @see loadByProperties()
   */
  public function loadBySfid(SFID $salesforce_id) {
    return $this->loadByProperties([
      'salesforce_id' => (string) $salesforce_id,
    ]);
  }

  /**
   * Load a single MappedObject by Mapping and SFID.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return MappedObjectInterface|null
   *
   * @see loadByProperties()
   */
  public function loadBySfidAndMapping(SFID $salesforce_id, SalesforceMappingInterface $mapping) {
    $result = $this->loadByProperties([
      'salesforce_id' => (string) $salesforce_id,
      'salesforce_mapping' => $mapping->id(),
    ]);
    return empty($result) ? NULL : reset($result);
  }

  /**
   * Set the "force_pull" column to TRUE for all mapped objects of the given
   * mapping.
   *
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *
   * @return $this
   */
  public function setForcePull(SalesforceMappingInterface $mapping) {
    $this->database->update($this->baseTable)
      ->condition('salesforce_mapping', $mapping->id())
      ->fields(['force_pull' => 1])
      ->execute();
    return $this;
  }

}
