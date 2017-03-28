<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce\Event\SalesforceBaseEvent;

/**
 *
 */
abstract class SalesforcePushEvent extends SalesforceBaseEvent {

  protected $mapping;
  protected $mapped_object;
  protected $entity;

  /**
   * {@inheritdoc}
   *
   * @param MappedObjectInterface $mapped_object
   * @param PushParams $params
   *   One of
   *     Drupal\salesforce_mapping\MappingConstants::
   *       SALESFORCE_MAPPING_SYNC_DRUPAL_CREATE
   *       SALESFORCE_MAPPING_SYNC_DRUPAL_UPDATE
   *       SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE
   */
  public function __construct(MappedObjectInterface $mapped_object) {
    $this->mapped_object = $mapped_object;
    $this->entity = ($mapped_object) ? $mapped_object->getMappedEntity() : NULL;
    $this->mapping = ($mapped_object) ? $mapped_object->getMapping() : NULL;
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

}
