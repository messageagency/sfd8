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
 *
 * Extends ConfigEntityStorage to add some commonly used convenience wrappers.
 *
 * @package Drupal\salesforce_mapping
 */
class MappedObjectStorage extends SqlContentEntityStorage {

  /**
   * MappedObjectStorage constructor.
   *
   * @param string $entity_type_id
   *   Entity type id.
   * @param \Drupal\Core\Database\Connection $database
   *   Database service.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   Entity manager service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Cache service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   Language service.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct($entity_type_id, Connection $database, EntityManagerInterface $entity_manager, CacheBackendInterface $cache, LanguageManagerInterface $language_manager) {
    // @TODO the $entity_type needs to be in the constructor and not
    // devrived from from $entity_type_id. This is because of the parent
    // class SqlContentEntityStorage's createInstance method, which while
    // ultimately calls it's own constructor through here, is calling this
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
   * @param string $entity_type_id
   *   Entity type id.
   * @param int|string $entity_id
   *   Entity id.
   *
   * @return \Drupal\salesforce_mapping\Entity\MappedObject[]
   *   Mapped objects.
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
   *   Drupal entity.
   *
   * @return \Drupal\salesforce_mapping\Entity\MappedObject[]
   *   Mapped objects.
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
   *   Drupal entity.
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   Salesforce Mapping.
   *
   * @return \Drupal\salesforce_mapping\Entity\MappedObjectInterface|null
   *   The matching Mapped Object, or null if none are found.
   *
   * @see loadByProperties()
   */
  public function loadByEntityAndMapping(EntityInterface $entity, SalesforceMappingInterface $mapping) {
    $result = $this->loadByProperties([
      'drupal_entity__target_type' => $entity->getEntityTypeId(),
      'drupal_entity__target_id' => $entity->id(),
      'salesforce_mapping' => $mapping->id(),
    ]);
    return empty($result) ? NULL : reset($result);
  }

  /**
   * Load MappedObjects by Salesforce ID.
   *
   * @param \Drupal\salesforce\SFID $salesforce_id
   *   Salesforce id.
   *
   * @return \Drupal\salesforce_mapping\Entity\MappedObjectInterface[]
   *   Matching mapped objects.
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
   * @param \Drupal\salesforce\SFID $salesforce_id
   *   Salesforce id.
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   Salesforce mapping.
   *
   * @return \Drupal\salesforce_mapping\Entity\MappedObjectInterface|null
   *   Mapped object, or null if none are found.
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
   * Set "force_pull" column to TRUE for mapped objects of the given mapping.
   *
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   Mapping.
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
