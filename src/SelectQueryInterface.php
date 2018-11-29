<?php

namespace Drupal\salesforce;

/**
 * A SOQL query interface.
 */
interface SelectQueryInterface {

  /**
   * Return the query as a string.
   *
   * @return string
   *   The url-encoded query string.
   */
  public function __toString();

}
