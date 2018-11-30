<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce_mapping\Entity\MappedObjectInterface;

/**
 * Push allowed event.
 */
class SalesforcePushAllowedEvent extends SalesforcePushOpEvent {

  /**
   * Indicates whether push is allowed to continue.
   *
   * @var bool
   */
  protected $pushAllowed;

  /**
   * SalesforcePushAllowedEvent dispatched before building PushParams.
   *
   * @param \Drupal\salesforce_mapping\Entity\MappedObjectInterface $mapped_object
   *   The mapped object.
   * @param string $op
   *   One of
   *     Drupal\salesforce_mapping\MappingConstants::
   *       SALESFORCE_MAPPING_SYNC_DRUPAL_CREATE
   *       SALESFORCE_MAPPING_SYNC_DRUPAL_UPDATE
   *       SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE.
   */
  public function __construct(MappedObjectInterface $mapped_object, $op) {
    parent::__construct($mapped_object, $op);
    $this->op = $op;
  }

  /**
   * Returns FALSE if push is disallowed.
   *
   * Note: a subscriber cannot "force" a push when any other subscriber has
   * disallowed it.
   *
   * @return false|null
   *   Returns FALSE if PUSH_ALLOWED event has been fired, and any subscriber
   *   wants to prevent push. Otherwise, returns NULL.
   */
  public function isPushAllowed() {
    return $this->pushAllowed === FALSE ? FALSE : NULL;
  }

  /**
   * Stop Salesforce record from being pushed.
   *
   * @return $this
   */
  public function disallowPush() {
    $this->pushAllowed = FALSE;
    return $this;
  }

}
