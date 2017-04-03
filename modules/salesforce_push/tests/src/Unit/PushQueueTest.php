<?php

namespace Drupal\Tests\salesforce_push\Unit;

use Drupal\salesforce_push\PushQueue;
use Drupal\Tests\UnitTestCase;

/**
 * Test Object instantitation
 *
 * @coversDefaultClass \Drupal\salesforce_push\PushQueue
 *
 * @group salesforce_push
 */

class PushQueueTest extends UnitTestCase {
  static $modules = ['salesforce_push'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
  }

  /**
   * @covers ::claimItems
   */
  public function testClaimItems() {
    // Test basic claim items
    // Test claim items with different mappings
    // Test claim items excluding failed items
  }

  /**
   * @covers ::processQueues
   */
  public function testProcessQueues() {
    // Test creating a queue processor
    // Test mapping frequency
    // Test mapping push limit
    // Test global push limit
    // Test queue processor throwing RequeueException
    // Test queue processor throwing SuspendQueueException
  }

  /**
   * @covers ::failItem
   */
  public function testFailItem() {
    // Test failed item gets its "fail" property incremented by 1
  }

}
