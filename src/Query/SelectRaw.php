<?php

namespace Drupal\salesforce\Query;

/**
 * Class SelectRaw to construct SOQL manually from a string.
 */
class SelectRaw implements SelectInterface {

  protected $query;

  /**
   * SelectRaw constructor.
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

  /**
   * @inheritDoc
   */
  public function getConditionString() {
    // TODO: Implement getConditionString() method.
  }

  /**
   * @inheritDoc
   */
  public function compileCondition(array $condition) {
    // TODO: Implement compileCondition() method.
  }

  /**
   * @inheritDoc
   */
  public function compileConditions() {
    // TODO: Implement compileConditions() method.
  }

  /**
   * @inheritDoc
   */
  public function __clone() {
    // TODO: Implement __clone() method.
  }

  /**
   * @inheritDoc
   */
  public function &getFields() {
    // TODO: Implement getFields() method.
  }

  /**
   * @inheritDoc
   */
  public function &getExpressions() {
    // TODO: Implement getExpressions() method.
  }

  /**
   * @inheritDoc
   */
  public function &getOrderBy() {
    // TODO: Implement getOrderBy() method.
  }

  /**
   * @inheritDoc
   */
  public function &getGroupBy() {
    // TODO: Implement getGroupBy() method.
  }

  /**
   * @inheritDoc
   */
  public function &getTables() {
    // TODO: Implement getTables() method.
  }

  /**
   * @inheritDoc
   */
  public function condition($field, $value = NULL, $operator = '=') {
    // TODO: Implement condition() method.
  }

  /**
   * @inheritDoc
   */
  public function isNull($field) {
    // TODO: Implement isNull() method.
  }

  /**
   * @inheritDoc
   */
  public function isNotNull($field) {
    // TODO: Implement isNotNull() method.
  }

  /**
   * @inheritDoc
   */
  public function alwaysFalse() {
    // TODO: Implement alwaysFalse() method.
  }

  /**
   * @inheritDoc
   */
  public function conditions() {
    // TODO: Implement conditions() method.
  }

  /**
   * @inheritDoc
   */
  public function where($snippet) {
    // TODO: Implement where() method.
  }

  /**
   * @inheritDoc
   */
  public function conditionGroupFactory($conjunction = 'AND') {
    // TODO: Implement conditionGroupFactory() method.
  }

  /**
   * @inheritDoc
   */
  public function andConditionGroup() {
    // TODO: Implement andConditionGroup() method.
  }

  /**
   * @inheritDoc
   */
  public function orConditionGroup() {
    // TODO: Implement orConditionGroup() method.
  }

  /**
   * @inheritDoc
   */
  public function addTable($table, $alias = NULL) {
    // TODO: Implement addTable() method.
  }

  /**
   * @inheritDoc
   */
  public function addField($table_alias, $field) {
    // TODO: Implement addField() method.
  }

  /**
   * @inheritDoc
   */
  public function fields($table_alias, array $fields) {
    // TODO: Implement fields() method.
  }

  /**
   * @inheritDoc
   */
  public function addExpression($expression) {
    // TODO: Implement addExpression() method.
  }

  /**
   * @inheritDoc
   */
  public function orderBy($field, $direction = 'ASC') {
    // TODO: Implement orderBy() method.
  }

  /**
   * @inheritDoc
   */
  public function range($start = NULL, $length = NULL) {
    // TODO: Implement range() method.
  }

  /**
   * @inheritDoc
   */
  public function groupBy($field) {
    // TODO: Implement groupBy() method.
  }

  /**
   * @inheritDoc
   */
  public function countQuery() {
    // TODO: Implement countQuery() method.
  }

  /**
   * @inheritDoc
   */
  public function havingCondition($field, $value = NULL, $operator = NULL) {
    // TODO: Implement havingCondition() method.
  }

  /**
   * @inheritDoc
   */
  public function &havingConditions() {
    // TODO: Implement havingConditions() method.
  }

  /**
   * @inheritDoc
   */
  public function having($snippet) {
    // TODO: Implement having() method.
  }

  /**
   * @inheritDoc
   */
  public function forUpdate($set = TRUE) {
    // TODO: Implement forUpdate() method.
  }


}
