<?php

namespace Drupal\salesforce_pull;

use Drupal\salesforce\SObject;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;

class PullQueueItem {

  /**
   * @var Drupal\salesforce\SObject
   */
  public $sobject;

  /**
   * @var string
   */
  public $mapping_id;

  /**
   * Whether to force pull for the given record.
   *
   * @var bool
   */
  public $force_pull;

  /**
   * @param SObject $sobject
   * @param SalesforceMappingInterface $mapping
   * @param bool $force_pull
   */
  public function __construct(SObject $sobject, SalesforceMappingInterface $mapping, $force_pull = FALSE) {
    $this->sobject = $sobject;
    $this->mapping_id = $mapping->id;
    $this->force_pull = $force_pull;
  }

}
