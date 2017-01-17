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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Class MappedObjectStorage.
 * Extends ConfigEntityStorage to add some commonly used convenience wrappers.
 *
 * @package Drupal\salesforce_mapping
 */
class SalesforceMappingStorage extends ConfigEntityStorage {

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
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type->id(),
      $container->get('config.factory'),
      $container->get('uuid'),
      $container->get('language_manager'),
      $container->get('entity.manager')
    );
  }

  /**
   * pass-through for loadMultipleMapping()
   */
  public function loadMappingByDrupal($entity_type_id) {
    return $this->loadByProperties(["drupal_entity_type" => $entity_type_id]);
  }

  /**
   * Return a unique list of mapped Salesforce object types.
   * @see loadMultipleMapping()
   * @throws EntityNotFoundException if no mappings have been created yet.
   */
  function getMappedSobjectTypes() {
    $object_types = [];
    $mappings = $this->loadByProperties();
    foreach ($mappings as $mapping) {
      $type = $mapping->getSalesforceObjectType();
      $object_types[$type] = $type;
    }
    return $object_types;
  }
}
