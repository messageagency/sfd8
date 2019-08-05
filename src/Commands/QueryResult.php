<?php

namespace Drupal\salesforce\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFieldsWithMetadata;
use Drupal\salesforce\Query\SelectInterface;
use Drupal\salesforce\Query\SelectResult;

/**
 * Adds structured metadata to RowsOfFieldsWithMetadata.
 */
class QueryResult extends RowsOfFieldsWithMetadata {

  protected $size;
  protected $total;
  protected $query;

  /**
   * QueryResult constructor.
   *
   * @param \Drupal\salesforce\Query\SelectInterface $query
   *   SOQL query.
   * @param \Drupal\salesforce\Query\SelectResult $queryResult
   *   SOQL result.
   */
  public function __construct(SelectInterface $query, SelectResult $queryResult) {
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
   * Getter for query size (total number of records returned).
   *
   * @return int
   *   The size.
   */
  public function getSize() {
    return $this->size;
  }

  /**
   * Getter for query total (total number of records available).
   *
   * @return mixed
   *   The total.
   */
  public function getTotal() {
    return $this->total;
  }

  /**
   * Getter for query.
   *
   * @return \Drupal\salesforce\Query\Select
   *   The query.
   */
  public function getQuery() {
    return $this->query;
  }

  /**
   * Get a prettified query.
   *
   * @return string
   *   Strip '+' escaping from the query.
   */
  public function getPrettyQuery() {
    return str_replace('+', ' ', (string) $this->query);
  }

}
