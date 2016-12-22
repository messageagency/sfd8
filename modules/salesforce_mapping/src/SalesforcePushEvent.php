<?php

namespace Drupal\salesforce_mapping;

use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;

class SalesforcePushEvent extends Event {

  protected $params;
  protected $mapping;
  protected $mapped_object;
  protected $entity;

  public function __construct(MappedObjectInterface $mapped_object = NULL, PushParams $params = NULL) {
    $this->mapped_object = $mapped_object;
    $this->params = $params;
    $this->entity = $params->getEntity();
    $this->mapping = $params->getMapping();
  }

  public function getEntity() {
    return $this->entity;
  }

  public function getMapping() {
    return $this->mapping;
  }

  public function getMappedObject() {
    return $this->mapped_object;
  }

  public function getParams() {
    return $this->params;
  }

}
