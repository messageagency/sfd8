<?php

use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

class PushQueueTest extends BrowserTestBase {

  public static $modules = ['typed_data', 'dynamic_entity_reference', 'salesforce_mapping', 'salesforce_mapping_test', 'salesforce_push'];

  public function testEnqueue() {
    /** @var \Drupal\salesforce_push\PushQueue $queue */
    $queue = \Drupal::service('queue.salesforce_push');
    $this->assertEquals(0, $queue->numberOfItems());

    Node::create([
        'type' => 'salesforce_mapping_test_content',
        'title' => 'Test Example',
      ]
    )->save();
    $this->assertEquals(1, $queue->numberOfItems());

    Node::create([
        'type' => 'salesforce_mapping_test_content',
        'title' => 'Test Example 2',
      ]
    )->save();
    $this->assertEquals(2, $queue->numberOfItems());

  }

}