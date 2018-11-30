<?php

namespace Drupal\salesforce;

/**
 * Allows for creating a select query by providing the SOQL string directly.
 */
class SelectQueryRaw implements SelectQueryInterface {

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
