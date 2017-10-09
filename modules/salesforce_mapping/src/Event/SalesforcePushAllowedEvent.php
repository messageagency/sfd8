<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce_mapping\Entity\MappedObjectInterface;

/**
 *
 */
class SalesforcePushAllowedEvent extends SalesforcePushOpEvent {

  protected $op;
  protected $push_allowed;

  /**
   * {@inheritdoc}
   *
   * SalesforcePushAllowedEvent is fired when PushParams are not available, for
   * example on SalesforceEvents::PUSH_ALLOWED, before any entities have been
   * loaded.
   *
   * @param \Drupal\salesforce_mapping\Entity\MappedObjectInterface $mapped_object
   * @param string $op
   *   One of
   *     Drupal\salesforce_mapping\MappingConstants::
   *       SALESFORCE_MAPPING_SYNC_DRUPAL_CREATE
   *       SALESFORCE_MAPPING_SYNC_DRUPAL_UPDATE
   *       SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE.
   */
  public function __construct(MappedObjectInterface $mapped_object, $op) {
    parent::__construct($mapped_object);
    $this->op = $op;
  }

  /**
   *
   */
  public function getOp() {
    return $this->op;
  }

  /**
   * Returns FALSE if PUSH_ALLOWED event has been fired, and any subscriber
   * wants to prevent push. Otherwise, returns NULL. Note: a subscriber cannot
   * "force" a push when any other subscriber which wants to prevent pushing.
   *
   * @return FALSE or NULL
   */
  public function isPushAllowed() {
    return $this->push_allowed === FALSE ? FALSE : NULL;
  }

  /**
   * Stop Salesforce record from being pushed.
   *
   * @return $this
   */
  public function disallowPush() {
    $this->push_allowed = FALSE;
    return $this;
  }

}
