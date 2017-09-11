<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_mapping\PushParams;

/**
 *
 */
class SalesforcePushParamsEvent extends SalesforcePushEvent {

  protected $params;

  /**
   * {@inheritdoc}
   *
   * @param \Drupal\salesforce_mapping\Entity\MappedObjectInterface $mapped_object
   * @param \Drupal\salesforce_mapping\PushParams $params
   */
  public function __construct(MappedObjectInterface $mapped_object, PushParams $params) {
    parent::__construct($mapped_object);
    $this->params = $params;
    $this->entity = ($params) ? $params->getDrupalEntity() : NULL;
    $this->mapping = ($params) ? $params->getMapping() : NULL;
  }

  /**
   * @return \Drupal\salesforce_mapping\PushParams
   */
  public function getParams() {
    return $this->params;
  }

}
