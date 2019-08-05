<?php

namespace Drupal\Tests\salesforce\Unit;

use Drupal\salesforce\Query\Select;
use Drupal\salesforce\Query\SelectResult;
use Drupal\Tests\UnitTestCase;

class SelectTest extends UnitTestCase {

  static public $modules = ['salesforce'];

  /**
   * Test that SOQL query syntax is generated as expected.
   *
   * @dataProvider queryTestDataProvider
   */
  public function queryTest($expectedValue, Select $query) {
    $this->assertEquals($expectedValue, (string) $query);
  }

  /**
   * Data provider for queryTest().
   *
   * @return array
   */
  public function queryTestDataProvider() {
    $queryData = [];

    $fieldsQuery = new Select('Account', 'a');
    $fieldsQuery
      ->addField('a', 'Id')
      ->fields('a', ['Name']);
    $fieldsSubquery = new Select('Contacts', 'c');
    $fieldsSubquery
      ->addField('c', 'Email')
      ->fields('c', ['Id', 'FirstName', 'LastName']);
    $fieldsQuery->addExpression($fieldsSubquery);
    $queryData[] = [
      'expectedValue' => 'SELECT a.Id, a.Name, (SELECT c.Email, c.Id, c.FirstName, c.LastName FROM Contacts c) FROM Account a',
      'query' => $fieldsQuery
    ];

    $conditionsQuery = new Select('Account', 'a');
    $conditionsQuery
      ->addField('a', 'Id')
      ->addField('a', 'Name');
    $conditionsSubquery = new Select('Opportunity', 'o');
    $conditionsSubquery
      ->addField('o', 'AccountId')
      ->condition('o.StageName', "'Closed Lost'", 'NOT LIKE')
      ->condition('Id', $conditionsSubquery, 'IN');
    $queryData[] = [
      'expectedValue' => 'SELECT a.Id, a.Name FROM Account a WHERE Id IN (SELECT o.AccountId FROM Opportunity o WHERE NOT (o.StageName LIKE \'Closed Lost\'))',
      'query' => $conditionsQuery
    ];

    $aggregationQuery = new Select('Opportunity', 'o');
    $aggregationQuery->addField('o', 'Name')
      ->addExpression('MAX(Amount)')
      ->addExpression('MIN(Amount)')
      ->addExpression('SUM(Amount)')
      ->groupBy('Name');
    $queryData[] = [
      'expectedValue' => 'SELECT o.Name, MAX(Amount), MIN(Amount), SUM(Amount) FROM Opportunity o GROUP BY Name',
      'query' => $aggregationQuery
    ];

    $countQuery = new Select('Account', 'a');
    $countQuery
      ->addField('a', 'Id')
      ->fields('a', ['Name']);
    $queryData[] = [
      'expectedValue' => 'SELECT COUNT() FROM Account a',
      'query' => $countQuery,
    ];

    $optionalParamsQuery = new Select('Contact', NULL, ['conjunction' => 'OR']);
    $optionalParamsQuery
      ->fields('Contact', ['FirstName', 'LastName'])
      ->range(5, 10)
      ->orderBy('Email')
      ->condition('Email', "''")
      ->condition('FirstName', "''");
    $queryData[] = [
      'expectedValue' => "SELECT Contact.FirstName, Contact.LastName FROM Contact WHERE (Email = '') OR (FirstName = '') ORDER BY Email ASC LIMIT 10 OFFSET 5",
      'query' => $optionalParamsQuery,
    ];

    $havingQuery = new Select('Account');
    $havingQuery
      ->addField('Account', 'Name')
      ->addExpression('Count(Id)')
      ->groupBy('Name')
      ->having('Count(Id) > 1')
      ->forUpdate();
    $queryData[] = [
      'expectedValue' => 'SELECT Account.Name, Count(Id) FROM Account GROUP BY Name HAVING (Count(Id) > 1) FOR UPDATE',
      'query' => $havingQuery
    ];

    $nullNotNullFalseQuery = new \Drupal\salesforce\Query\Select('Contact');
    $nullNotNullFalseQuery->addField('Contact', 'FirstName');
    $nullNotNullFalseQuery->addTable('Contact.Account', 'a');
    $nullNotNullFalseQuery->addField('Contact', 'Account.Name');
    $nullNotNullFalseQuery->isNull('c.Email');
    $nullNotNullFalseQuery->isNotNull('a.Name');
    $nullNotNullFalseQuery->alwaysFalse();
    $queryData[] = [
      'expectedValue' => 'SELECT Contact.FirstName, Contact.Account.Name FROM Contact, Contact.Account a WHERE (c.Email IS NULL) AND (a.Name IS NOT NULL) AND ((1 = 0))',
      'query' => $nullNotNullFalseQuery,
    ];

    return $queryData;
  }

}