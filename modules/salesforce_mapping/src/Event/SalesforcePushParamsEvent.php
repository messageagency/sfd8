<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_mapping\PushParams;

/**
 * Push params event.
 */
class SalesforcePushParamsEvent extends SalesforcePushEvent {

  /**
   * Push params.
   *
   * @var \Drupal\salesforce_mapping\PushParams
   */
  protected $params;

  /**
   * SalesforcePushParamsEvent constructor.
   *
   * @param \Drupal\salesforce_mapping\Entity\MappedObjectInterface $mapped_object
   *   Mapped object.
   * @param \Drupal\salesforce_mapping\PushParams $params
   *   Push params.
   */
  public function __construct(MappedObjectInterface $mapped_object, PushParams $params) {
    parent::__construct($mapped_object);
    $this->params = $params;
    $this->entity = ($params) ? $params->getDrupalEntity() : NULL;
    $this->mapping = ($params) ? $params->getMapping() : NULL;
  }

  /**
   * Getter.
   *
   * @return \Drupal\salesforce_mapping\PushParams
   *   The push param data to be sent to Salesforce.
   */
  public function getParams() {
    return $this->params;
  }

}
