<?php
namespace Drupal\Tests\salesforce\salesforce_pull;

use Drupal\Tests\UnitTestCase;
use Drupal\salesforce_pull\QueueHandler;
use Drupal\salesforce\SObject;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_pull\PullQueueItem;

/**
 * Test Object instantitation
 *
 * @group salesforce_pull
 */

class PullQueueItemTest extends UnitTestCase {
  static $modules = ['salesforce_pull'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $prophecy = $this->prophesize(RestClient::CLASS);
    $sfapi = $prophecy->reveal();
    $qh = QueueHandler::create($sfapi);
  }

  /**
   * Test object instantiation
   */
  public function testObject() {
    $this->assertTrue($qh instanceof QueueHandler);
  }
}
