<?php

namespace Drupal\Tests\salesforce\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\salesforce\SFID;
use Drupal\salesforce\Query\SelectResult;

/**
 * Test Object instantitation.
 *
 * @group salesforce_pull
 */
class SelectResultTest extends UnitTestCase {
  static public $modules = ['salesforce'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $result = [
      'totalSize' => 2,
      'done' => TRUE,
      'records' => [
        [
          'Id' => '1234567890abcde',
          'attributes' => ['type' => 'dummy'],
          'name' => 'Example',
        ],
        [
          'Id' => '1234567890abcdf',
          'attributes' => ['type' => 'dummy'],
          'name' => 'Example2',
        ],
      ],
    ];
    $this->sqr = new SelectResult($result);
  }

  /**
   * Test object instantiation with good resultd.
   */
  public function testGoodId() {
    $this->assertTrue($this->sqr instanceof SelectResult);
  }

  /**
   * Test object instantiation with non-existent ID.
   */
  public function testNoId() {
    $sfid = new SFID('1234567890abcdg');
    $this->assertFalse($this->sqr->record($sfid));
  }

}
