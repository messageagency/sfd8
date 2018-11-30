<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce\Event\SalesforceBaseEvent;

/**
 * Salesforce pull event.
 */
class SalesforcePullEvent extends SalesforceBaseEvent {

  /**
   * The mapping responsible for this pull.
   *
   * @var \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface
   */
  protected $mapping;

  /**
   * The mapped object associated with this pull.
   *
   * @var \Drupal\salesforce_mapping\Entity\MappedObjectInterface
   */
  protected $mappedObject;

  /**
   * The Drupal entity into which the data is being pulled.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected $entity;

  /**
   * The pull operation.
   *
   * One of:
   * \Drupal\salesforce_mapping\MappingConstants::SALESFORCE_MAPPING_SYNC_SF_CREATE
   * \Drupal\salesforce_mapping\MappingConstants::SALESFORCE_MAPPING_SYNC_SF_UPDATE
   * \Drupal\salesforce_mapping\MappingConstants::SALESFORCE_MAPPING_SYNC_SF_DELETE.
   *
   * @var string
   */
  protected $op;

  /**
   * SalesforcePullEvent constructor.
   *
   * @param \Drupal\salesforce_mapping\Entity\MappedObjectInterface $mappedObject
   *   The mapped object.
   * @param string $op
   *   The operation.
   */
  public function __construct(MappedObjectInterface $mappedObject, $op) {
    $this->mappedObject = $mappedObject;
    $this->entity = $mappedObject->getMappedEntity();
    $this->mapping = $mappedObject->getMapping();
    $this->op = $op;
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
   *   The mapping interface.
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
   * Getter for the pull operation.
   *
   * One of:
   * \Drupal\salesforce_mapping\MappingConstants::SALESFORCE_MAPPING_SYNC_SF_CREATE
   * \Drupal\salesforce_mapping\MappingConstants::SALESFORCE_MAPPING_SYNC_SF_UPDATE
   * \Drupal\salesforce_mapping\MappingConstants::SALESFORCE_MAPPING_SYNC_SF_DELETE.
   *
   * @var string
   *   The op.
   */
  public function getOp() {
    return $this->op;
  }

}
