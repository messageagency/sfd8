<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce\SelectQueryInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce\Event\SalesforceBaseEvent;

/**
 * Pull query event.
 */
class SalesforceQueryEvent extends SalesforceBaseEvent {

  /**
   * The query to be issued.
   *
   * @var \Drupal\salesforce\SelectQueryInterface
   */
  protected $query;

  /**
   * The mapping.
   *
   * @var \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface
   */
  protected $mapping;

  /**
   * SalesforceQueryEvent constructor.
   *
   * @param \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface $mapping
   *   The mapping.
   * @param \Drupal\salesforce\SelectQueryInterface $query
   *   The query.
   */
  public function __construct(SalesforceMappingInterface $mapping, SelectQueryInterface $query) {
    $this->mapping = $mapping;
    $this->query = $query;
  }

  /**
   * Getter.
   *
   * @return \Drupal\salesforce\SelectQueryInterface
   *   The query.
   */
  public function getQuery() {
    return $this->query;
  }

  /**
   * Getter.
   *
   * @return \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface
   *   The mapping.
   */
  public function getMapping() {
    return $this->mapping;
  }

}
