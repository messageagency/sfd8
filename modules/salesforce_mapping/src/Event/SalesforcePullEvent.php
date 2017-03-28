<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce\Event\SalesforceBaseEvent;

/**
 *
 */
class SalesforcePullEvent extends SalesforceBaseEvent {

  protected $params;
  protected $mapping;
  protected $mapped_object;
  protected $entity;
  protected $op;

  /**
   * {@inheritdoc}
   *
   * @param MappedObjectInterface $mapped_object 
   * @param string $op
   *   One of 
   *     Drupal\salesforce_mapping\MappingConstants::
   *       SALESFORCE_MAPPING_SYNC_SF_CREATE
   *       SALESFORCE_MAPPING_SYNC_SF_UPDATE
   *       SALESFORCE_MAPPING_SYNC_SF_DELETE
   */
  public function __construct(MappedObjectInterface $mapped_object, $op) {
    $this->mapped_object = $mapped_object;
    $this->entity = $mapped_object->getMappedEntity();
    $this->mapping = $mapped_object->getMapping();
    $this->op = $op;
  }

  /**
   * @return EntityInterface (from PushParams)
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * @return SalesforceMappingInterface (from PushParams)
   */
  public function getMapping() {
    return $this->mapping;
  }

  /**
   * @return MappedObjectInterface
   */
  public function getMappedObject() {
    return $this->mapped_object;
  }

  public function getOp() {
    return $this->op;
  }

}
