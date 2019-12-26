<?php

namespace Drupal\salesforce;

/**
 * Class SelectQueryRaw to construct SOQL manually from a string.
 */
class SelectQueryRaw implements SelectQueryInterface {

  /**
   * The query.
   *
   * @var string
   */
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
