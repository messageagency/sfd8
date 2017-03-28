<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce\SelectQuery;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce\Event\SalesforceBaseEvent;

/**
 *
 */
class SalesforceQueryEvent extends SalesforceBaseEvent {

  protected $query;
  protected $mapping;

  /**
   * {@inheritdoc}
   *
   * @param MappedObjectInterface $mapped_object

   */
  public function __construct(SalesforceMappingInterface $mapping, SelectQuery $query) {
    $this->mapping = $mapping;
    $this->query = $query;
  }

  /**
   * @return EntityInterface (from PushParams)
   */
  public function getQuery() {
    return $this->query;
  }

  /**
   * @return SalesforceMappingInterface (from PushParams)
   */
  public function getMapping() {
    return $this->mapping;
  }

}
