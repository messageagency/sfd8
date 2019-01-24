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

  /**
   * Add a condition to the query.
   *
   * Valid operators include '=', '!=', '<', '<=', '>', '>=', 'LIKE', 'IN',
   * 'NOT IN', 'INCLUDES', 'EXCLUDES'
   *
   * @param $field
   * @param mixed|null $value
   * @param string $operator
   *
   * @return mixed
   */
  public function condition($field, $value = NULL, $operator = '=');

  public function where();

  public function with();

  public function limit($number_of_rows);

  public function groupBy($field);

  /**
   * Sets sort order for
   *
   * @param string $field
   * Salesforce field to order by.
   * @param string $direction
   *
   * @param string $nulls
   *
   * @return mixed
   */
  public function orderBy($field, $direction = 'ASC', $nulls = 'FIRST');

  public function offset($number_of_rows_to_skip);

  public function having();
}
