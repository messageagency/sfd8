<?php

namespace Drupal\salesforce_pull;

use Drupal\salesforce\SObject;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;

/**
 * A salesforce_pull_queue item.
 */
class PullQueueItem {

  /**
   * The salesforce object data.
   *
   * @var \Drupal\salesforce\SObject
   */
  protected $sobject;

  /**
   * The mapping id corresponding to this pull.
   *
   * @var string
   */
  protected $mappingId;

  /**
   * Whether to force pull for the given record.
   *
   * @var bool
   */
  protected $forcePull;

  /**
   * Construct a pull queue item.
   *
   * @param \Drupal\salesforce\SObject $sobject
   *   Salesforce data.
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   Mapping.
   * @param bool $force_pull
   *   Force data to be pulled, ignoring any timestamps.
   */
  public function __construct(SObject $sobject, SalesforceMappingInterface $mapping, $force_pull = FALSE) {
    $this->sobject = $sobject;
    $this->mappingId = $mapping->id;
    $this->forcePull = $force_pull;
  }

  /**
   * Getter.
   *
   * @return \Drupal\salesforce\SObject
   *   Salesforce data.
   */
  public function getSobject() {
    return $this->sobject;
  }

  /**
   * Getter.
   *
   * @return string
   *   Salesforce mapping id.
   */
  public function getMappingId() {
    // Legacy backwards compatibility.
    // @TODO remove for 8.x-3.3
    if (property_exists($this, 'mapping_id')) {
      return $this->mapping_id;
    }
    return $this->mappingId;
  }

  /**
   * Getter.
   *
   * @return bool
   *   Force pull.
   */
  public function getForcePull() {
    // Legacy backwards compatibility.
    // @TODO remove for 8.x-3.3
    if (property_exists($this, 'force_pull')) {
      return $this->force_pull;
    }
    return $this->forcePull;
  }

}
