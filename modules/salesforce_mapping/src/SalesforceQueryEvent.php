<?php

namespace Drupal\salesforce_mapping;

use Symfony\Component\EventDispatcher\Event;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;

/**
 *
 */
class SalesforceQueryEvent extends Event {

  protected $query;
  protected $mapping;

  /**
   * {@inheritdoc}
   *
   * @param MappedObjectInterface $mapped_object 

   */
  public function __construct(SalesforceMappingInterface $mapping, SelectQuery $query) {
    $this->mapping = $mapping;
    $this->query = $query
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
