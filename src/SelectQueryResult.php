<?php

namespace Drupal\salesforce;

/**
 * Class SelectQueryResult.
 *
 * @package Drupal\salesforce
 */
class SelectQueryResult {

  /**
   * Total number of records for this query.
   *
   * @var int
   */
  protected $totalSize;

  /**
   * Indicates whether the current result set is the complete set.
   *
   * @var bool
   */
  protected $done;

  /**
   * The current result set.
   *
   * @var array
   */
  protected $records;

  /**
   * If there are additional records, the URL of the query to fetch them.
   *
   * @var string
   */
  protected $nextRecordsUrl;

  /**
   * SelectQueryResult constructor.
   *
   * @param array $results
   *   The query results.
   */
  public function __construct(array $results) {
    $this->totalSize = $results['totalSize'];
    $this->done = $results['done'];
    if (!$this->done) {
      $this->nextRecordsUrl = $results['nextRecordsUrl'];
    }
    $this->records = [];
    foreach ($results['records'] as $record) {
      if ($sobj = SObject::createIfValid($record)) {
        $this->records[$record['Id']] = $sobj;
      }
    }
  }

  /**
   * Convenience method a SelectQueryResult from a single SObject record.
   *
   * @param \Drupal\salesforce\SObject $record
   *   The record to be created.
   *
   * @return \Drupal\salesforce\SelectQueryResult
   *   A query result containing the given record.
   */
  public static function createSingle(SObject $record) {
    $results = [
      'totalSize' => 1,
      'done' => TRUE,
      'records' => []
    ];
    $result = new static($results);
    $result->records[(string) $record->id()] = $record;
    return $result;
  }

  /**
   * Getter.
   *
   * @return string|null
   *   The next record url, or null.
   */
  public function nextRecordsUrl() {
    return $this->nextRecordsUrl;
  }

  /**
   * Getter.
   *
   * @return int
   *   The query size. For a single-page query, will be equal to total.
   */
  public function size() {
    return $this->totalSize;
  }

  /**
   * Indicates whether the query is "done", or has more results to be fetched.
   *
   * @return bool
   *   Return FALSE if the query has more pages of results.
   */
  public function done() {
    return $this->done;
  }

  /**
   * The results.
   *
   * @return \Drupal\salesforce\SObject[]
   *   The result records.
   */
  public function records() {
    return $this->records;
  }

  /**
   * Fetch a particular record given its SFID.
   *
   * @param \Drupal\salesforce\SFID $id
   *   The SFID.
   *
   * @return \Drupal\salesforce\SObject|false
   *   The record, or FALSE if no record exists for given id.
   */
  public function record(SFID $id) {
    return isset($this->records[(string) $id]) ? $this->records[(string) $id] : FALSE;
  }

}
