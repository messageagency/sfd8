<?php

namespace Drupal\salesforce\Query;

use Drupal\Core\Database\Query\Condition;

/**
 * Class Select.
 *
 * @package Drupal\salesforce
 */
class Select implements SelectInterface {

  /**
   * The salesforce tables (object type) against which to query.
   *
   * @var array
   */
  protected $tables;

  /**
   * The fields to SELECT.
   *
   * @var array
   */
  protected $fields = [];

  /**
   * The expressions to SELECT as virtual fields.
   *
   * @var array
   */
  protected $expressions = [];

  /**
   * The fields by which to order this query.
   *
   * This is an associative array. The keys are the fields to order, and the value
   * is the direction to order, either ASC or DESC.
   *
   * @var array
   */
  protected $order = [];

  /**
   * The fields by which to group.
   *
   * @var array
   */
  protected $group = [];

  /**
   * The conditional object for the HAVING clause.
   *
   * @var \Drupal\Core\Database\Query\Condition
   */
  protected $having;

  /**
   * The condition object for this query.
   *
   * Condition handling is handled via composition.
   *
   * @var \Drupal\Core\Database\Query\Condition
   */
  protected $condition;

  /**
   * The range limiters for this query.
   *
   * @var array
   */
  protected $range;

  /**
   * Indicates if preExecute() has already been called.
   * @var bool
   */
  protected $prepared = FALSE;

  /**
   * The FOR UPDATE status
   *
   * @var bool
   */
  protected $forUpdate = FALSE;

  /**
   * Whether the conditions have been compiled.
   *
   * @var bool
   */
  protected $compiled = FALSE;

  /**
   * The compiled condition string.
   *
   * @var string
   */
  protected $conditionString;

  /**
   * The compiled having string.
   *
   * @var string
   */
  protected $havingString;

  /**
   * Query options.
   *
   * @var array
   */
  protected $queryOptions;

  /**
   * Provides a map of condition operators to condition operator options.
   */
  protected static $conditionOperatorMap = [
    'IN' => ['delimiter' => ', ', 'prefix' => '(', 'postfix' => ')'],
    'NOT IN' => ['delimiter' => ', ', 'prefix' => '(', 'postfix' => ')'],
    'INCLUDES' => ['delimiter' => ', ', 'prefix' => '(', 'postfix' => ')'],
    'EXCLUDES' => ['delimiter' => ', ', 'prefix' => '(', 'postfix' => ')'],
    // These ones are here for performance reasons.
    'NOT LIKE' => [],
    'LIKE' => [],
    '=' => [],
    '!=' => [],
    '<' => [],
    '>' => [],
    '>=' => [],
    '<=' => [],
  ];

  /**
   * Select constructor.
   *
   * @param string $table
   *   Salesforce table (AKA object type) to query.
   */
  public function __construct($table, $alias = NULL, $options = []) {
    $this->tables[$alias] = [
      'table' => $table,
      'alias' => $alias,
    ];

    $conjunction = isset($options['conjunction']) ? $options['conjunction'] : 'AND';
    $this->condition = new Condition($conjunction);
    $this->having = new Condition($conjunction);
    $this->queryOptions = $options;
  }

  /**
   * @deprecated addCondition is deprecated and will be removed in 8.x-4.0. Use ::condition instead.
   */
  public function addCondition($field, $value, $operator = '=') {
    return $this->condition($field, $value, $operator);
  }

  /**
   * {@inheritdoc}
   */
  public function getConditionString() {
    return $this->conditionString;
  }

  /**
   * {@inheritdoc}
   */
  public function compileCondition(array $condition) {
    if ($condition['field'] instanceof Select) {
      // Left hand part is a structured condition or a subquery. Compile,
      // put brackets around it (if it is a query).
      $condition['field']->compileConditions();
      $field_fragment = '(' . $condition['field']->getConditionString() . ')';

      // If the operator and value were not passed in to the
      // ConditionInterface::condition() method (and thus have the
      // default value as defined over there) it is assumed to be a valid
      // condition on its own: ignore the operator and value parts.
      $ignore_operator = $condition['operator'] === '=' && $condition['value'] === NULL;
    }
    elseif (!isset($condition['operator'])) {
      // Left hand part is a literal string added with the
      // @see ConditionInterface::where() method. Put brackets around
      // the snippet and ignore the operator and value parts.
      $field_fragment = '(' . $condition['field'] . ')';
      $ignore_operator = TRUE;
    }
    else {
      // Left hand part is a normal field. Add it as is.
      $field_fragment = $condition['field'];
      $ignore_operator = FALSE;
    }

    // Process operator.
    if ($ignore_operator) {
      $operator = ['operator' => '', 'use_value' => FALSE];
    }
    else {
      // Remove potentially dangerous characters.
      // If something passed in an invalid character stop early, so we
      // don't rely on a broken SQL statement when we would just replace
      // those characters.
      if (stripos($condition['operator'], 'UNION') !== FALSE || strpbrk($condition['operator'], '[-\'"();') !== FALSE) {
        $this->changed = TRUE;
        // Provide a string which will result into an empty query result.
        $this->stringVersion = '( AND 1 = 0 )';

        // Conceptually throwing an exception caused by user input is bad
        // as you result into a WSOD, which depending on your webserver
        // configuration can result into the assumption that your site is
        // broken.
        // On top of that the database API relies on __toString() which
        // does not allow to throw exceptions.
        trigger_error('Invalid characters in query operator: ' . $condition['operator'], E_USER_ERROR);
        return;
      }
      $operator = isset(static::$conditionOperatorMap[$condition['operator']]) ? static::$conditionOperatorMap[$condition['operator']] : [];
      $operator += ['operator' => $condition['operator']];
    }
    // Add defaults.
    $operator += [
      'prefix' => '',
      'postfix' => '',
      'delimiter' => '',
      'use_value' => TRUE,
    ];
    $operator_fragment = $operator['operator'];

    // Process value.
    $value_fragment = '';
    if ($operator['use_value']) {
      // For simplicity, we first convert to an array, so that we can handle
      // the single and multi value cases the same.
      if (!is_array($condition['value'])) {
        $condition['value'] = [$condition['value']];
      }
      // Process all individual values.
      $value_fragment = [];
      foreach ($condition['value'] as $value) {
          // Right hand part is a normal value.
          $value_fragment[] = $value;
      }
      $value_fragment = $operator['prefix'] . implode($operator['delimiter'], $value_fragment) . $operator['postfix'];
    }

    // Concatenate the left hand part, operator and right hand part.
    if ($operator['operator'] == 'NOT LIKE') {
      $operator_fragment = 'LIKE';
    }
    $ret = trim(implode(' ', [$field_fragment, $operator_fragment, $value_fragment]));
    if ($operator['operator'] == 'NOT LIKE') {
      // NOT LIKE isn't actually an operator in Salesforce.
      // Have to negate the entire condition.
      $ret = "NOT ($ret)";
    }
    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function compileConditions() {
    $condition_fragments = [];
    $conditions = $this->condition->conditions();
    $conjunction = $conditions['#conjunction'];
    unset($conditions['#conjunction']);
    foreach ($conditions as $condition) {
      $condition_fragments[] = $this->compileCondition($condition);
    }
    // Concatenate all conditions using the conjunction and brackets around
    // the individual conditions to assure the proper evaluation order.
    $this->conditionString = count($condition_fragments) > 1 ? '(' . implode(") $conjunction (", $condition_fragments) . ')' : implode($condition_fragments);

    $having_fragments = [];
    $having = $this->having->conditions();
    $conjunction = $having['#conjunction'];
    unset($having['#conjunction']);
    foreach ($having as $condition) {
      $having_fragments[] = $this->compileCondition($condition);
    }
    // Concatenate all conditions using the conjunction and brackets around
    // the individual conditions to assure the proper evaluation order.
    $this->havingString = count($having_fragments) > 1 ? '(' . implode(") $conjunction (", $having_fragments) . ')' : implode($having_fragments);
    $this->changed = FALSE;

  }

  /**
   * {@inheritdoc}
   */
  public function __toString() {
    if (!$this->compiled) {
      $this->compileConditions();
    }

    // SELECT
    $query = 'SELECT ';

    // FIELDS and EXPRESSIONS
    $fields = [];
    foreach ($this->fields as $field_name => $field) {
      $fields[] = $field['table'] . '.' . $field['field'];
    }
    foreach ($this->expressions as $expression) {
      if ($expression instanceof Select) {
        $expression = '(' . $expression . ')';
      }
      $fields[] = $expression;
    }
    $query .= implode(', ', $fields);

    // FROM - We presume all queries have a FROM, as any query that doesn't won't need the query builder anyway.
    $query .= " FROM ";
    $tables = [];
    foreach ($this->tables as $table) {
      // Don't use the AS keyword for table aliases, as some
      // databases don't support it (e.g., Oracle).
      $tables[] = $table['table'] . (!empty($table['alias']) ? ' ' . $table['alias'] : '');
    }
    $query .= implode(', ', $tables);

    // WHERE
    if (count($this->condition)) {
      $query .= " WHERE " . $this->conditionString;
    }

    // GROUP BY
    if ($this->group) {
      $query .= " GROUP BY " . implode(', ', $this->group);
    }

    // HAVING
    if (count($this->having)) {
      $query .= " HAVING " . $this->havingString;
    }

    // ORDER BY
    if ($this->order) {
      $query .= " ORDER BY ";
      $fields = [];
      foreach ($this->order as $field => $direction) {
        $fields[] = $field . ' ' . $direction;
      }
      $query .= implode(', ', $fields);
    }

    // RANGE
    // There is no universal SQL standard for handling range or limit clauses.
    // Fortunately, all core-supported databases use the same range syntax.
    // Databases that need a different syntax can override this method and
    // do whatever alternate logic they need to.
    if (!empty($this->range)) {
      $query .= " LIMIT " . (int) $this->range['length'] . " OFFSET " . (int) $this->range['start'];
    }

    if ($this->forUpdate) {
      $query .= ' FOR UPDATE';
    }

    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function __clone() {
    $this->condition = clone($this->condition);
    $this->having = clone($this->having);
  }

  /**
   * {@inheritdoc}
   */
  public function &getTables() {
    return $this->tables;
  }

  /**
   * {@inheritdoc}
   */
  public function &getFields() {
    return $this->fields;
  }

  /**
   * {@inheritdoc}
   */
  public function &getExpressions() {
    return $this->expressions;
  }

  /**
   * {@inheritdoc}
   */
  public function &getOrderBy() {
    return $this->order;
  }

  /**
   * {@inheritdoc}
   */
  public function &getGroupBy() {
    return $this->group;
  }

  /**
   * {@inheritdoc}
   */
  public function condition($field, $value = NULL, $operator = '=') {
    $this->condition->condition($field, $value, $operator);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isNull($field) {
    $this->condition->isNull($field);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isNotNull($field) {
    $this->condition->isNotNull($field);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function alwaysFalse() {
    $this->condition->alwaysFalse();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function &conditions() {
    return $this->condition->conditions();
  }

  /**
   * {@inheritdoc}
   */
  public function where($snippet) {
    $this->condition->where($snippet);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function conditionGroupFactory($conjunction = 'AND') {
    return new Condition($conjunction);
  }

  /**
   * {@inheritdoc}
   */
  public function andConditionGroup() {
    return $this->conditionGroupFactory('AND');
  }

  /**
   * {@inheritdoc}
   */
  public function orConditionGroup() {
    return $this->conditionGroupFactory('OR');
  }

  /**
   * {@inheritdoc}
   */
  public function addTable($table, $alias = NULL) {
    if (empty($alias)) {
      $alias = $table;
    }

    // If that is already used, just add a counter until we find an unused alias.
    $alias_candidate = $alias;
    $count = 2;
    while (!empty($this->tables[$alias_candidate])) {
      $alias_candidate = $alias . '_' . $count++;
    }
    $alias = $alias_candidate;

    $this->tables[$alias] = [
      'table' => $table,
      'alias' => $alias,
    ];
    return $alias;
  }

  /**
   * {@inheritdoc}
   */
  public function addField($table_alias, $field) {
    $this->fields[$field] = [
      'field' => $field,
      'table' => $table_alias,
    ];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function fields($table_alias, array $fields) {
    foreach ($fields as $field) {
      $this->addField($table_alias, $field);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function addExpression($expression) {
    $this->expressions[] = $expression;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function orderBy($field, $direction = 'ASC') {
    // Only allow ASC and DESC, default to ASC.
    $direction = strtoupper($direction) == 'DESC' ? 'DESC' : 'ASC';
    $this->order[$field] = $direction;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function range($start = NULL, $length = NULL) {
    $this->range = $start !== NULL ? ['start' => $start, 'length' => $length] : [];
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function groupBy($field) {
    $this->group[$field] = $field;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function countQuery() {
    $count = $this->prepareCountQuery();
    $count->addExpression('COUNT()');
    return $count;
  }

  /**
   * Prepares a count query from the current query object.
   *
   * @return \Drupal\salesforce\Query\Select
   *   A new query object ready to have COUNT() performed on it.
   */
  protected function prepareCountQuery() {
    // Create our new query object that we will mutate into a count query.
    $count = clone($this);

    $group_by = $count->getGroupBy();
    $having = $count->havingConditions();

    if (!isset($having[0])) {
      // We can zero-out existing fields and expressions that are not used by a
      // GROUP BY or HAVING. Fields listed in a GROUP BY or HAVING clause need
      // to be present in the query.
      $fields =& $count->getFields();
      foreach (array_keys($fields) as $field) {
        if (empty($group_by[$field])) {
          unset($fields[$field]);
        }
      }

      $expressions =& $count->getExpressions();
      foreach (array_keys($expressions) as $field) {
        if (empty($group_by[$field])) {
          unset($expressions[$field]);
        }
      }
    }

    // Ordering a count query is a waste of cycles, and breaks on some
    // databases anyway.
    $orders = &$count->getOrderBy();
    $orders = [];

    return $count;
  }


  /**
   * {@inheritdoc}
   */
  public function havingCondition($field, $value = NULL, $operator = NULL) {
    $this->having->condition($field, $value, $operator);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function &havingConditions() {
    return $this->having->conditions();
  }

  /**
   * {@inheritdoc}
   */
  public function having($snippet) {
    $this->having->where($snippet);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function forUpdate($set = TRUE) {
    if (isset($set)) {
      $this->forUpdate = $set;
    }
    return $this;
  }

}
