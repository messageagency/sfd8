<?php

namespace Drupal\Tests\salesforce_push\Functional;

use Drupal\node\Entity\Node;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\Tests\BrowserTestBase;

/**
 * Test PushQueue.
 *
 * @group salesforce_push
 */
class PushQueueTest extends BrowserTestBase {

  /**
   * Default theme required for D9.
   *
   * @var string
   */
  protected $defaultTheme  = 'stark';

  /**
   * Required modules.
   *
   * @var array
   */
  public static $modules = [
    'typed_data',
    'dynamic_entity_reference',
    'salesforce_mapping',
    'salesforce_mapping_test',
    'salesforce_push',
  ];

  /**
   * Test queue features.
   *
   * Test creation of queue items.
   * Test mocked push queue create and creation of mapped objects.
   * Test mocked push queue update and update of mapped objects.
   * Test deletion of entities and corresponding deletion of SF related records.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testQueue() {
    /** @var \Drupal\salesforce_push\PushQueue $queue */
    $queue = \Drupal::service('queue.salesforce_push');
    $mapping = SalesforceMapping::load('test_mapping');
    // Set mapping to async, otherwise the push will be attempted immediately.
    $mapping->set('async', TRUE);
    $mapping->save();

    /** @var \Drupal\salesforce_mapping\MappedObjectStorage $mappedObjectStorage */
    $mappedObjectStorage = \Drupal::entityTypeManager()->getStorage('salesforce_mapped_object');
    $this->assertEquals(0, $queue->numberOfItems());

    $node1 = Node::create([
      'type' => 'salesforce_mapping_test_content',
      'title' => 'Test Example',
    ]
    );
    $node1->save();
    $this->assertEquals(1, $queue->numberOfItems());

    $node2 = Node::create([
      'type' => 'salesforce_mapping_test_content',
      'title' => 'Test Example 2',
    ]
    );
    $node2->save();
    $this->assertEquals(2, $queue->numberOfItems());

    // Two items are queued. Run the queue and ensure that mapped objects are
    // created from our mocked results.
    $queue->processQueue($mapping);
    $this->assertEquals(0, $queue->numberOfItems());

    $mappedObject1 = $mappedObjectStorage->loadByEntityAndMapping($node1, $mapping);
    $this->assertNotNull($mappedObject1);
    $mappedObject1Vid = $mappedObject1->getRevisionId();

    $mappedObject2 = $mappedObjectStorage->loadByEntityAndMapping($node2, $mapping);
    $this->assertNotNull($mappedObject2);
    $mappedObject2Vid = $mappedObject2->getRevisionId();

    // Update those nodes and test that they get re-queued.
    $node1->setTitle('Updated Title')->save();
    $this->assertEquals(1, $queue->numberOfItems());

    $node2->setTitle('Updated Title 2')->save();
    $this->assertEquals(2, $queue->numberOfItems());

    // Run the queue again and test that mapped objects get updated.
    $queue->processQueue($mapping);
    $this->assertEquals(0, $queue->numberOfItems());

    // Make sure a new revision was created, implying the update.
    $mappedObjectStorage->resetCache([$mappedObject1->id(), $mappedObject2->id()]);

    $mappedObject1 = $mappedObjectStorage->loadByEntityAndMapping($node1, $mapping);
    $mappedObject1UpdatedVid = $mappedObject1->getRevisionId();
    $this->assertNotEquals($mappedObject1Vid, $mappedObject1UpdatedVid);

    $mappedObject2 = $mappedObjectStorage->loadByEntityAndMapping($node2, $mapping);
    $mappedObject2UpdatedVid = $mappedObject2->getRevisionId();
    $this->assertNotEquals($mappedObject2Vid, $mappedObject2UpdatedVid);

    // Delete nodes and test that corresponding records are deleted.
    $node1->delete();
    $node2->delete();

    // Make sure the mapped object is not deleted yet.
    $mappedObjectStorage->resetCache([$mappedObject1->id(), $mappedObject2->id()]);
    $mappedObject1 = $mappedObjectStorage->load($mappedObject1->id());
    $this->assertNotNull($mappedObject1);
    $mappedObject2 = $mappedObjectStorage->load($mappedObject2->id());
    $this->assertNotNull($mappedObject2);

    // Make sure the delete queue items were created.
    $this->assertEquals(2, $queue->numberOfItems());

    // Process the queue to make sure the deletes get sent.
    $queue->processQueue($mapping);
    $this->assertEquals(0, $queue->numberOfItems());

    // Finally, make sure the mapped objects have been deleted.
    $mappedObjectStorage->resetCache([$mappedObject1->id(), $mappedObject2->id()]);
    $this->assertNull($mappedObjectStorage->load($mappedObject1->id()));
    $this->assertNull($mappedObjectStorage->load($mappedObject2->id()));
  }

}
