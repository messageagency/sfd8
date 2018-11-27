<?php

namespace Drupal\salesforce;

/**
 *
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
