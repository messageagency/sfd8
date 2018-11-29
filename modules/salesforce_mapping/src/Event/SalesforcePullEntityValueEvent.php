<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce\Event\SalesforceBaseEvent;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface;

/**
 * Pull entity event.
 */
class SalesforcePullEntityValueEvent extends SalesforceBaseEvent {

  /**
   * The value of the field to be assigned.
   *
   * @var mixed
   */
  protected $entityValue;

  /**
   * The field plugin responsible for pulling the data.
   *
   * @var \Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface
   */
  protected $fieldPlugin;

  /**
   * The mapped object, or mapped object stub.
   *
   * @var \Drupal\salesforce_mapping\Entity\MappedObjectInterface
   */
  protected $mappedObject;

  /**
   * The mapping responsible for this pull.
   *
   * @var \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface
   */
  protected $mapping;

  /**
   * The Drupal entity, or entity stub.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * SalesforcePullEntityValueEvent constructor.
   *
   * @param mixed $value
   *   The value to be assigned.
   * @param \Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface $fieldPlugin
   *   The field plugin.
   * @param \Drupal\salesforce_mapping\Entity\MappedObjectInterface $mappedObject
   *   The mapped object.
   */
  public function __construct(&$value, SalesforceMappingFieldPluginInterface $fieldPlugin, MappedObjectInterface $mappedObject) {
    $this->entityValue = $value;
    $this->fieldPlugin = $fieldPlugin;
    $this->mappedObject = $mappedObject;
    $this->entity = $mappedObject->getMappedEntity();
    $this->mapping = $mappedObject->getMapping();
  }

  /**
   * Getter.
   *
   * @return mixed
   *   The value to be pulled and assigned to the Drupal entity.
   */
  public function getEntityValue() {
    return $this->entityValue;
  }

  /**
   * Getter.
   *
   * @return \Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface
   *   The field plugin.
   */
  public function getFieldPlugin() {
    return $this->fieldPlugin;
  }

  /**
   * Getter.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity.
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * Getter.
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface
   *   The mapping.
   */
  public function getMapping() {
    return $this->mapping;
  }

  /**
   * Getter.
   *
   * @return \Drupal\salesforce_mapping\Entity\MappedObjectInterface
   *   The mapped object.
   */
  public function getMappedObject() {
    return $this->mappedObject;
  }

}
