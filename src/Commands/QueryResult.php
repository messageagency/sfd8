<?php

namespace Drupal\salesforce\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFieldsWithMetadata;
use Drupal\salesforce\SelectQueryInterface;
use Drupal\salesforce\SelectQueryResult;

class QueryResult extends RowsOfFieldsWithMetadata {

  protected $size;
  protected $total;
  protected $query;

  public function __construct(SelectQueryInterface $query, SelectQueryResult $queryResult) {
    print_r($queryResult->records());
    $data = [];
    foreach ($queryResult->records() as $id => $record) {
      $data[$id] = $record->fields();
    }
    parent::__construct($data);
    $this->size = count($queryResult->records());
    $this->total = $queryResult->size();
    $this->query = $query;
  }

  /**
   * @return int
   */
  public function getSize() {
    return $this->size;
  }

  /**
   * @return mixed
   */
  public function getTotal() {
    return $this->total;
  }

  /**
   * @return \Drupal\salesforce\SelectQuery
   */
  public function getQuery() {
    return $this->query;
  }

  public function getPrettyQuery() {
    return str_replace('+', ' ', (string) $this->query);
  }

}