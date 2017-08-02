<?php

namespace Drupal\salesforce_mapping;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\ContentEntityInterface;
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
   // CacheBackendInterface
   // DatabaseBackendFactory
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
   * Load mapped object by entity type id and entity id
   *
   * @pararm string entity_type_id
   *
   * @param int/string entity_id
   *
   * @see loadByProperties()
   */
  public function loadByDrupal($entity_type_id, $entity_id) {
    return $this->loadByProperties([
      'entity_type_id' => $entity_type_id,
      'entity_id' => $entity_id,
    ]);
  }

  /**
   * Load mapped objects by Drupal Entity
   *
   * @param ContentEntityInterface $entity
   *
   * @see loadByProperties()
   */
  public function loadByEntity(ContentEntityInterface $entity) {
    return $this->loadByProperties([
      'entity_type_id' => $entity->getEntityTypeId(),
      'entity_id' => $entity->id(),
    ]);
  }

  /**
   * Load mapped objects by Salesforce ID
   *
   * @param SFID $salesforce_id
   *
   * @see loadByProperties()
   */
  public function loadBySfid(SFID $salesforce_id) {
    return $this->loadByProperties([
      'salesforce_id' => (string)$salesforce_id,
    ]);
  }

  /**
   * Set the "force_pull" column to TRUE for all mapped objects of the given
   * mapping
   *
   * @param SalesforceMappingInterface $mapping
   *
   * @return $this
   */
  public function setForcePull(SalesforceMappingInterface $mapping) {
    $query = $this->database->update($this->baseTable)
      ->condition('salesforce_mapping', $mapping->id())
      ->fields(array('force_pull' => 1))
      ->execute();
    return $this;
  }

}
