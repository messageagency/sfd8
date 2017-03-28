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
   * @param SObject $sobject
   * @param SalesforceMappingInterface $mapping
   */
  public function __construct(SObject $sobject, SalesforceMappingInterface $mapping) {
    $this->sobject = $sobject;
    $this->mapping_id = $mapping->id;
  }

}
