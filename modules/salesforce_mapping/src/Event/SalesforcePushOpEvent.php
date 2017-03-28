<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce_mapping\Entity\MappedObjectInterface;

/**
 *
 */
class SalesforcePushOpEvent extends SalesforcePushEvent {

  protected $op;

  /**
   * {@inheritdoc}
   *
   * SalesforcePushOpEvent is fired when PushParams are not available, for
   * example on SalesforceEvents::PUSH_ALLOWED, before any entities have been
   * loaded.
   *
   * @param MappedObjectInterface $mapped_object
   * @param string $op
   *   One of
   *     Drupal\salesforce_mapping\MappingConstants::
   *       SALESFORCE_MAPPING_SYNC_DRUPAL_CREATE
   *       SALESFORCE_MAPPING_SYNC_DRUPAL_UPDATE
   *       SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE
   */
  public function __construct(MappedObjectInterface $mapped_object, $op) {
    parent::__construct($mapped_object);
    $this->op = $op;
  }

  public function getOp() {
    return $this->op;
  }

}
