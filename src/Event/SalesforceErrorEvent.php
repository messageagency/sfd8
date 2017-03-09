<?php

namespace Drupal\salesforce\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 *
 */
class SalesforceErrorEvent extends Event {

  /**
   * {@inheritdoc}
   */
  public function __construct($message) {
    $this->message = $mapped_object;
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
