<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce_mapping\Entity\MappedObjectInterface;

/**
 * Push op event.
 */
class SalesforcePushOpEvent extends SalesforcePushEvent {

  /**
   * The pull operation.
   *
   * One of:
   * \Drupal\salesforce_mapping\MappingConstants::SALESFORCE_MAPPING_SYNC_SF_CREATE
   * \Drupal\salesforce_mapping\MappingConstants::SALESFORCE_MAPPING_SYNC_SF_UPDATE
   * \Drupal\salesforce_mapping\MappingConstants::SALESFORCE_MAPPING_SYNC_SF_DELETE.
   *
   * @var string
   */
  protected $op;

  /**
   * SalesforcePushOpEvent dispatched when PushParams are not available.
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
    parent::__construct($mapped_object);
    $this->op = $op;
  }

  /**
   * Getter for the pull operation.
   *
   * One of:
   * \Drupal\salesforce_mapping\MappingConstants::SALESFORCE_MAPPING_SYNC_SF_CREATE
   * \Drupal\salesforce_mapping\MappingConstants::SALESFORCE_MAPPING_SYNC_SF_UPDATE
   * \Drupal\salesforce_mapping\MappingConstants::SALESFORCE_MAPPING_SYNC_SF_DELETE.
   *
   * @return string
   *   The op.
   */
  public function getOp() {
    return $this->op;
  }

}
