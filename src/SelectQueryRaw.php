<?php

namespace Drupal\salesforce;

use Drupal\salesforce\SelectQueryBase;

class SelectQueryRaw extends SelectQueryBase {

  protected $query;

  /**
   * SelectQueryRaw constructor.
   *
   * @param string $query
   *   The SOQL query.
   */
  public function __construct($query) {
    $this->query = $query;
  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    return str_replace(' ', '+', $this->query);
  }
}
