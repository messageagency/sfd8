<?php

namespace Drupal\salesforce_mapping;

use Symfony\Component\EventDispatcher\Event;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;

/**
 *
 */
class SalesforcePushParamsEvent extends SalesforcePushEvent {

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
   */
  public function __construct(MappedObjectInterface $mapped_object, PushParams $params) {
    parent::__construct($mapped_object);
    $this->params = $params;
    $this->entity = ($params) ? $params->getDrupalEntity() : null;
    $this->mapping = ($params) ? $params->getMapping() : null;
  }

  /**
   * @return PushParams
   */
  public function getParams() {
    return $this->params;
  }

}
