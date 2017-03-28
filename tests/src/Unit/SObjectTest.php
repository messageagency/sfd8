<?php
namespace Drupal\Tests\salesforce\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\salesforce\SObject;

/**
 * Test Object instantitation
 *
 * @group salesforce_pull
 */

class SObjectTest extends UnitTestCase {
  static $modules = ['salesforce'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
  }

  /**
   * Test object instantiation
   */
  public function testObject() {
    $sobject = new SObject(['id' => '1234567890abcde', 'attributes' => ['type' => 'dummy',]]);
    $this->assertTrue($sobject instanceof SObject);
    $this->assertEquals('1234567890abcdeAAA', $sobject->id());
  }

  /**
   * Test object instantiation wth no ID
   * @expectedException Exception
   */
  public function testObjectNoID() {
    $sobject = new SObject(['attributes' => ['type' => 'dummy',]]);
  }

  /**
   * Test object instantiation with bad ID
   * @expectedException Exception
   */
  public function testObjectBadID() {
    $sobject = new SObject(['id' => '1234567890', 'attributes' => ['type' => 'dummy',]]);
  }

  /**
   * Test object instantiation with no type
   * @expectedException Exception
   */
  public function testObjectNoType() {
    $sobject = new SObject(['id' => '1234567890abcde']);
  }

  /**
   * Test invalid field call
   * @expectedException Exception
   */
  public function testFieldNotExists() {
    $sobject = new SObject(['id' => '1234567890abcde', 'attributes' => ['type' => 'dummy',]]);
    $field = $sobject->field('key');
  }

  /**
   * Test valid field call
   */
  public function testFieldExists() {
    $sobject = new SObject([
      'id' => '1234567890abcde',
      'attributes' => ['type' => 'dummy',],
      'name' => 'Example',
    ]);
    $this->assertEquals('Example',$sobject->field('name'));
  }

}
