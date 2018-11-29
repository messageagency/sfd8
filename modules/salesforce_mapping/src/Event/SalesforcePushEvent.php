<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce\Event\SalesforceBaseEvent;

/**
 * Push event.
 */
abstract class SalesforcePushEvent extends SalesforceBaseEvent {

  /**
   * The mapping.
   *
   * @var \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface
   */
  protected $mapping;

  /**
   * The mapped object.
   *
   * @var \Drupal\salesforce_mapping\Entity\MappedObjectInterface
   */
  protected $mappedObject;

  /**
   * The Drupal entity.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected $entity;

  /**
   * SalesforcePushEvent constructor.
   *
   * @param \Drupal\salesforce_mapping\Entity\MappedObjectInterface $mappedObject
   *   The mapped object.
   */
  public function __construct(MappedObjectInterface $mappedObject) {
    $this->mappedObject = $mappedObject;
    $this->entity = ($mappedObject) ? $mappedObject->getMappedEntity() : NULL;
    $this->mapping = ($mappedObject) ? $mappedObject->getMapping() : NULL;
  }

  /**
   * Getter.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
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
