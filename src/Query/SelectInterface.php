<?php

namespace Drupal\salesforce\Query;


/**
 * Class Select.
 *
 * @package Drupal\salesforce
 */
interface SelectInterface {

  /**
   * Getter for conditionString property.
   *
   * Property conditionString is the formatted condition string for this query.
   *
   * @return string
   *   The condition string.
   */
  public function getConditionString();

  /**
   * Recursive helper for compileConditions.
   *
   * @param array $condition
   *   Condition array for compilation.
   *
   * @return string
   *   The compiled condition string.
   */
  public function compileCondition(array $condition);

  /**
   * Compile all conditions for the query.
   *
   * Assigns values to properties `conditionString` and `havingString`.
   * Sets property `changed` to FALSE. If any subsequent calls alter conditions,
   * `changed` will be reset to TRUE indicating that conditions need to be
   * recompiled before executing the query.
   */
  public function compileConditions();

  /**
   * Returns a string representation of how the query will be executed in SOQL.
   *
   * @return string
   *   The Select Query object expressed as a string.
   */
  public function __toString();

  /**
   * Clone magic method.
   *
   * Select queries have dependent objects that must be deep-cloned.  The
   * connection object itself, however, should not be cloned as that would
   * duplicate the connection itself.
   */
  public function __clone();

  /**
   * Returns a reference to the fields array for this query.
   *
   * Because this method returns by reference, alter hooks may edit the fields
   * array directly to make their changes. If just adding fields, however, the
   * use of addField() is preferred.
   *
   * Note that this method must be called by reference as well:
   *
   * @code
   * $fields =& $query->getFields();
   * @endcode
   *
   * @return array
   *   A reference to the fields array structure.
   */
  public function &getFields();

  /**
   * Returns a reference to the expressions array for this query.
   *
   * Because this method returns by reference, alter hooks may edit the expressions
   * array directly to make their changes. If just adding expressions, however, the
   * use of addExpression() is preferred.
   *
   * Note that this method must be called by reference as well:
   *
   * @code
   * $fields =& $query->getExpressions();
   * @endcode
   *
   * @return array
   *   A reference to the expression array structure.
   */
  public function &getExpressions();

  /**
   * Returns a reference to the order by array for this query.
   *
   * Because this method returns by reference, alter hooks may edit the order-by
   * array directly to make their changes. If just adding additional ordering
   * fields, however, the use of orderBy() is preferred.
   *
   * Note that this method must be called by reference as well:
   *
   * @code
   * $fields =& $query->getOrderBy();
   * @endcode
   *
   * @return array
   *   A reference to the expression array structure.
   */
  public function &getOrderBy();

  /**
   * Returns a reference to the group-by array for this query.
   *
   * Because this method returns by reference, alter hooks may edit the group-by
   * array directly to make their changes. If just adding additional grouping
   * fields, however, the use of groupBy() is preferred.
   *
   * Note that this method must be called by reference as well:
   *
   * @code
   * $fields =& $query->getGroupBy();
   * @endcode
   *
   * @return
   *   A reference to the group-by array structure.
   */
  public function &getGroupBy();

  /**
   * Returns a reference to the tables array for this query.
   *
   * Because this method returns by reference, alter hooks may edit the tables
   * array directly to make their changes. If just adding tables, however, the
   * use of the join() methods is preferred.
   *
   * Note that this method must be called by reference as well:
   *
   * @code
   * $tables =& $query->getTables();
   * @endcode
   *
   * @return
   *   A reference to the tables array structure.
   */
  public function &getTables();

  /**
   * Add a condition to the query.
   *
   * Roughly equivalent to ConditionInterface::condition, but with many
   * restrictions and constraints specific to SOQL.
   *
   * @see \Drupal\Core\Database\Query\ConditionInterface::condition
   *
   * @return $this
   */
  public function condition($field, $value = NULL, $operator = '=');

  /**
   * Sets a condition that the specified field be NULL.
   *
   * @param string|\Drupal\salesforce\Query\Select $field
   *   The name of the field or a subquery to check.
   *
   * @return $this
   */
  public function isNull($field);

  /**
   * Sets a condition that the specified field be NOT NULL.
   *
   * @param string|\Drupal\salesforce\Query\Select $field
   *   The name of the field or a subquery to check.
   *
   * @return $this
   */
  public function isNotNull($field);

  /**
   * Sets a condition that is always false.
   *
   * @return $this
   */
  public function alwaysFalse();

  /**
   * Gets the, possibly nested, list of conditions in this conditional clause.
   *
   * This method returns by reference. That allows alter hooks to access the
   * data structure directly and manipulate it before it gets compiled.
   *
   * The data structure that is returned is an indexed array of entries, where
   * each entry looks like the following:
   * @code
   * array(
   *   'field' => $field,
   *   'value' => $value,
   *   'operator' => $operator,
   * );
   * @endcode
   *
   * In the special case that $operator is NULL, the $field is taken as a raw
   * SQL snippet (possibly containing a function) and $value is an associative
   * array of placeholders for the snippet.
   *
   * There will also be a single array entry of #conjunction, which is the
   * conjunction that will be applied to the array, such as AND.
   *
   * @return array
   *   The, possibly nested, list of all conditions (by reference).
   */
  public function conditions();

  /**
   * Adds an arbitrary WHERE clause to the query.
   *
   * @param string $snippet
   *   A portion of a WHERE clause as a prepared statement.
   *
   * @return \Drupal\Core\Database\Query\ConditionInterface
   *   The called object.
   */
  public function where($snippet);

  /**
   * Creates an object holding a group of conditions.
   *
   * See andConditionGroup() and orConditionGroup() for more.
   *
   * @param $conjunction
   *   - AND (default): this is the equivalent of andConditionGroup().
   *   - OR: this is the equivalent of orConditionGroup().
   *
   * @return \Drupal\Core\Database\Query\ConditionInterface
   *   An object holding a group of conditions.
   */
  public function conditionGroupFactory($conjunction = 'AND');

  /**
   * Creates a new group of conditions ANDed together.
   *
   * @return \Drupal\Core\Database\Query\ConditionInterface
   */
  public function andConditionGroup();

  /**
   * Creates a new group of conditions ORed together.
   *
   * @return \Drupal\Core\Database\Query\ConditionInterface
   */
  public function orConditionGroup();

  /**
   * Add a table to the query.
   *
   * Since SOQL does not use joins, we expose another means of adding tables to
   * the query.
   *
   * @return $this
   */
  public function addTable($table, $alias = NULL);

  /**
   * Add a field to the query, given its corresponding table alias.
   *
   * @return $this
   */
  public function addField($table_alias, $field);

  /**
   * Add an array of fields to the query, given their corresponding table alias.
   *
   * @return $this
   */
  public function fields($table_alias, array $fields);

  /**
   * Add an arbitrary expression to the query.
   *
   * @return $this
   */
  public function addExpression($expression);

  /**
   * Add an `ORDER BY` statement to the query and a order direction.
   *
   * @return $this
   */
  public function orderBy($field, $direction = 'ASC');

  /**
   * Add a `LIMIT` or `OFFSET` to the query.
   *
   * @return $this
   */
  public function range($start = NULL, $length = NULL);

  /**
   * Add a `GROUP BY` statement to the query.
   *
   * @return $this
   */
  public function groupBy($field);

  /**
   * Return a `COUNT()` version of this query.
   *
   * @return \Drupal\salesforce\Query\Select
   *   The count query.
   */
  public function countQuery();

  /**
   * Add a `HAVING` condition to the query.
   *
   * @return $this
   */
  public function havingCondition($field, $value = NULL, $operator = NULL);

  /**
   * Gets a list of all conditions in the HAVING clause.
   *
   * This method returns by reference. That allows alter hooks to access the
   * data structure directly and manipulate it before it gets compiled.
   *
   * @return array
   *   An array of conditions.
   *
   * @see \Drupal\Core\Database\Query\ConditionInterface::conditions()
   */
  public function &havingConditions();

  /**
   * Add an arbitrary `HAVING` statement to the query.
   *
   * @return $this
   */
  public function having($snippet);

  /**
   * Add a `FOR UPDATE` statement to the query.
   *
   * @return $this
   */
  public function forUpdate($set = TRUE);

}
