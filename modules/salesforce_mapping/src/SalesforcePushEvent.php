<?php

namespace Drupal\salesforce_mapping;

use Symfony\Component\EventDispatcher\Event;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;

/**
 *
 */
abstract class SalesforcePushEvent extends Event {

  protected $params;
  protected $mapping;
  protected $mapped_object;
  protected $entity;
  protected $op;

  /**
   * {@inheritdoc}
   *
   * @param MappedObjectInterface $mapped_object
   * @param PushParams $params
   * @param string $op
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
