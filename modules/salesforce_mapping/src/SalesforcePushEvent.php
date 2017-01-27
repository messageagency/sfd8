<?php

namespace Drupal\salesforce_mapping;

use Symfony\Component\EventDispatcher\Event;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;

/**
 *
 */
class SalesforcePushEvent extends Event {

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
  public function __construct(MappedObjectInterface $mapped_object = NULL, PushParams $params = NULL, $op = NULL) {
    $this->mapped_object = $mapped_object;
    $this->params = $params;
    $this->entity = ($params) ? $params->getDrupalEntity() : null;
    $this->mapping = ($params) ? $params->getMapping() : null;
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

  /**
   * @return PushParams
   */
  public function getParams() {
    return $this->params;
  }

  public function getOp() {
    return $this->op;
  }

}
