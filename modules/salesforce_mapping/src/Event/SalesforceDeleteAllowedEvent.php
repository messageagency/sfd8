<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce\Event\SalesforceBaseEvent;

/**
 * Delete allowed event.
 */
class SalesforceDeleteAllowedEvent extends SalesforceBaseEvent {

  /**
   * Indicates whether delete is allowed to continue.
   *
   * @var bool
   */
  protected $deleteAllowed;

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
   * SalesforceDeleteAllowedEvent dispatched before deleting an entity.
   *
   * @param \Drupal\salesforce_mapping\Entity\MappedObjectInterface $mapped_object
   *   The mapped object.
   */
  public function __construct(MappedObjectInterface $mapped_object) {
    $this->mappedObject = $mapped_object;
    $this->entity = ($mapped_object) ? $mapped_object->getMappedEntity() : NULL;
    $this->mapping = ($mapped_object) ? $mapped_object->getMapping() : NULL;
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

  /**
   * Returns FALSE if delete is disallowed.
   *
   * Note: a subscriber cannot "force" a delete when any other subscriber has
   * disallowed it.
   *
   * @return false|null
   *   Returns FALSE if DELETE_ALLOWED event has been fired, and any subscriber
   *   wants to prevent delete. Otherwise, returns NULL.
   */
  public function isDeleteAllowed() {
    return $this->deleteAllowed === FALSE ? FALSE : NULL;
  }

  /**
   * Stop Salesforce record from being deleted.
   *
   * @return $this
   */
  public function disallowDelete() {
    $this->deleteAllowed = FALSE;
    return $this;
  }

}
