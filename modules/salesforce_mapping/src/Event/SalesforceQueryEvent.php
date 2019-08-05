<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce\Query\SelectInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce\Event\SalesforceBaseEvent;

/**
 * Pull query event.
 */
class SalesforceQueryEvent extends SalesforceBaseEvent {

  /**
   * The query to be issued.
   *
   * @var \Drupal\salesforce\Query\SelectInterface
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
   * @param \Drupal\salesforce\Query\SelectInterface $query
   *   The query.
   */
  public function __construct(SalesforceMappingInterface $mapping, SelectInterface $query) {
    $this->mapping = $mapping;
    $this->query = $query;
  }

  /**
   * Getter.
   *
   * @return \Drupal\salesforce\Query\SelectInterface
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
