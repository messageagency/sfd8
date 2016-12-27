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

  /**
   * {@inheritdoc}
   *
   * @param MappedObjectInterface $mapped_object 
   * @param PushParams $params 
   */
  public function __construct(MappedObjectInterface $mapped_object = NULL, PushParams $params = NULL) {
    $this->mapped_object = $mapped_object;
    $this->params = $params;
    $this->entity = $params->getDrupalEntity();
    $this->mapping = $params->getMapping();
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

}
