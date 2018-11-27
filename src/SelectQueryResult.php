<?php

namespace Drupal\salesforce;

/**
 * Class SelectQueryResult.
 *
 * @package Drupal\salesforce
 */
class SelectQueryResult {

  protected $totalSize;
  protected $done;
  protected $records;
  protected $nextRecordsUrl;

  /**
   * SelectQueryResult constructor.
   *
   * @param array $results
   */
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

  /**
   * @return mixed
   */
  public function nextRecordsUrl() {
    return $this->nextRecordsUrl;
  }

  /**
   * @return mixed
   */
  public function size() {
    return $this->totalSize;
  }

  /**
   * @return mixed
   */
  public function done() {
    return $this->done;
  }

  /**
   * @return \Drupal\salesforce\SObject[]
   */
  public function records() {
    return $this->records;
  }

  /**
   * @param \Drupal\salesforce\SFID $id
   *
   * @return mixed
   * @throws \Exception
   */
  public function record(SFID $id) {
    if (!isset($this->records[(string) $id])) {
      throw new \Exception('No record found');
    }
    return $this->records[(string) $id];
  }

}
