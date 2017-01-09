<?php
/**
 * @file
 * Class representing a Salesforce SELECT SOQL query.
 */

namespace Drupal\salesforce;

class SelectQueryResult {
  
  protected $totalSize;
  protected $done;
  protected $records;
  protected $nextRecordsUrl;

  public function __construct(array $results) {
    $this->totalSize = $results['totalSize'];
    $this->done = $results['done'];
    if (!$this->done) {
      $this->nextRecordsUrl = $results['nextRecordsUrl'];
    }
    $this->records = [];
    foreach ($results['records'] as $record) {
      $this->records[$record['Id']] = new SObject($record);
    }
  }

  public function nextRecordsUrl() {
    return $this->nextRecordsUrl;
  }

  public function size() {
    return $this->totalSize;
  }

  public function done() {
    return $this->done;
  }

  public function records() {
    return $this->records;
  }

  public function record(SFID $id) {
    if (!isset($this->records[(string)$id])) {
      throw new \Exception('No record found');
    }
    return $this->records[(string)$id];
  }

}