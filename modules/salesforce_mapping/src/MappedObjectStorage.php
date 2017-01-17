<?php

namespace Drupal\salesforce_mapping;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\salesforce\EntityNotFoundException;
use Drupal\salesforce\SFID;

/**
 * Class MappedObjectStorage.
 * Extends ConfigEntityStorage to add some commonly used convenience wrappers.
 *
 * @package Drupal\salesforce_mapping
 */
class MappedObjectStorage extends ConfigEntityStorage {

  use ThrowsOnLoadTrait;

  /**
   * Drupal\Core\Config\ConfigFactory definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\Component\Uuid\Php definition.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * Drupal\Core\Language\LanguageManager definition.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Drupal\Core\Entity\EntityManager definition.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * {@inheritdoc}
   */
  public function __construct($entity_type_id, ConfigFactoryInterface $config_factory, UuidInterface $uuid_service, LanguageManagerInterface $language_manager, EntityManagerInterface $entity_manager) {
    $entity_type = $entity_manager->getDefinition($entity_type_id);
    parent::__construct($entity_type, $config_factory, $uuid_service, $language_manager);
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
  public function loadMappedObjectByDrupal($entity_type_id, $entity_id) {
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
  public function loadMappedObjectByEntity(ContentEntityInterface $entity) {
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
  public function loadMappedObjectBySfid(SFID $salesforce_id) {
    return $this->loadByProperties([
      'salesforce_id' => (string)$salesforce_id,
    ]);
  }

}
