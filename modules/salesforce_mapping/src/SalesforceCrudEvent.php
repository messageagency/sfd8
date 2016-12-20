<?php

namespace Drupal\salesforce_mapping;

use Symfony\Component\EventDispatcher\Event;
use Drupal\salesforce_mapping\Entity;

class SalesforceCrudEvent extends Event {

  protected $params;
  protected $mapping;
  protected $mapped_object;
  protected $entity;

  public function __construct(EntityInterface $entity, SalesforceMappingInterface $mapping, MappedObjectInterface $mapped_object = NULL, PushParams $params = NULL) {
    $this->entity = $entity;
    $this->mapping = $mapping;
    $this->mapped_object = $mapped_object;
    $this->params = $params;
  }

  public getEntity() {
    return $this->entity;
  }

  public getMapping() {
    return $this->mapping;
  }

  public getMappedObject() {
    return $this->mapped_object;
  }

  public getParams() {
    return $this->params;
  }

}