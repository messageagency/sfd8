<?php

namespace Drupal\salesforce;

/**
 * Class SelectQuery.
 *
 * @package Drupal\salesforce
 */
class SelectQuery implements SelectQueryInterface {

  /**
   * Fields to be selected.
   *
   * @var array
   */
  public $fields = [];

  /**
   * Order-by statements.
   *
   * @var array
   */
  public $order = [];

  /**
   * Objct type name, e.g. Contact, Account, etc.
   *
   * @var string
   */
  public $objectType;

  /**
   * Limit query result to this number.
   *
   * @var int
   */
  public $limit;

  /**
   * Condition statements.
   *
   * @var array
   */
  public $conditions = [];

  /**
   * SelectQuery constructor.
   *
   * @param string $object_type
   *   Salesforce object type to query.
   */
  public function __construct($object_type = '') {
    $this->objectType = $object_type;
  }

  /**
   * Add a condition to the query.
   *
   * @param string $field
   *   Field name.
   * @param mixed $value
   *   Condition value. If an array, it will be split into quote enclosed
   *   strings separated by commas inside of parenthesis. Note that the caller
   *   must enclose the value in quotes as needed by the SF API.
   *   NOTE: It is the responsibility of the caller to escape any single-quotes
   *   inside of string values.
   * @param string $operator
   *   Conditional operator. One of '=', '!=', '<', '>', 'LIKE, 'IN', 'NOT IN'.
   *
   * @return $this
   */
  public function addCondition($field, $value, $operator = '=') {
    if (is_array($value)) {

      $value = "('" . implode("','", $value) . "')";

      // Set operator to IN if wasn't already changed from the default.
      if ($operator == '=') {
        $operator = 'IN';
      }
    }

    $this->conditions[] = [
      'field' => $field,
      'operator' => $operator,
      'value' => $value,
    ];
    return $this;
  }

  /**
   * Implements PHP's magic toString().
   *
   * Function to convert the query to a string to pass to the SF API.
   *
   * @return string
   *   SOQL query ready to be executed the SF API.
   */
   // @codingStandardsIgnoreStart
  public function __toString() {

    $query = 'SELECT+';
    $query .= implode(',', array_unique($this->fields));
    $query .= "+FROM+" . $this->objectType;

    if (count($this->conditions) > 0) {
      $where = [];
      foreach ($this->conditions as $condition) {
        $where[] = implode('+', $condition);
      }
      $query .= '+WHERE+' . implode('+AND+', $where);
    }

    if ($this->order) {
      $query .= "+ORDER BY+";
      $fields = [];
      foreach ($this->order as $field => $direction) {
        $fields[] = $field . ' ' . $direction;
      }
      $query .= implode(',+', $fields);
    }

    if ($this->limit) {
      $query .= "+LIMIT+" . (int) $this->limit;
    }

    return $query;
  }
  // @codingStandardsIgnoreEnd
}
