<?php

namespace Drupal\salesforce_pull;

use Drupal\salesforce\SObject;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;

class PullQueueItem {
  public $sobject;
  public $mapping_id;
  public function __construct(SObject $sobject, SalesforceMappingInterface $mapping) {
    $this->sobject = $sobject;
    $this->mapping_id = $mapping->id();
  }
}