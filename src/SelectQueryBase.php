<?php

namespace Drupal\salesforce;

/**
 * Class SelectQueryBase.
 *
 * Provides SOQL escape method to use in child classes.
 *
 * @package Drupal\salesforce
 */
abstract class SelectQueryBase implements SelectQueryInterface {

  /**
   * Returns a value safe to use in a SOQL query.
   *
   * If there are no values that require escaping this will return the initial
   * value, otherwise it escapes ' marks and wraps the string in them.  If this
   * process triggers a SOQL error the problem is unsafe values were provided
   * that created a syntax error during execution.
   *
   * @param string $value
   *   The value to be used in a SOQL query.
   * @return string
   *   SOQL safe string.
   */
  public function escapeSoqlValue($value) {
    if (strpos($value, "'") === FALSE) {
      return $value;
    }
    return "'" . str_replace("'", "\'", $value) . "'";
  }

}
