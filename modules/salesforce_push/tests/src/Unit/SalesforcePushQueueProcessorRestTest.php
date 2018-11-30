<?php

namespace Drupal\Tests\salesforce_push\Unit;

use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\salesforce_push\Plugin\SalesforcePushQueueProcessor\Rest;
use Drupal\salesforce_push\PushQueueInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Entity\EntityManagerInterface;

/**
 * Test SalesforcePushQueueProcessor plugin Rest.
 *
 * @coversDefaultClass \Drupal\salesforce_push\Plugin\SalesforcePushQueueProcessor\Rest
 *
 * @group salesforce_pull
 */
class SalesforcePushQueueProcessorRestTest extends UnitTestCase {
  static public $modules = ['salesforce_pull'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->entityType = 'default';

    $this->queue = $this->getMock(PushQueueInterface::CLASS);
    $this->client = $this->getMock(RestClientInterface::CLASS);
    $this->eventDispatcher = $this->getMock(EventDispatcherInterface::CLASS);
    $this->eventDispatcher->expects($this->any())
      ->method('dispatch')
      ->willReturn(NULL);
    $this->entity_manager = $this->getMock(EntityManagerInterface::class);

    $this->string_translation = $this->getMock(TranslationInterface::class);

    $this->mapping = $this->getMock(SalesforceMappingInterface::CLASS);

    $this->mapping->expects($this->any())
      ->method('__get')
      ->with($this->equalTo('drupal_entity_type'))
      ->willReturn($this->entityType);

    $this->mappingStorage = $this->getMock(ConfigEntityStorageInterface::CLASS);
    $this->mappingStorage->expects($this->any())
      ->method('load')
      ->willReturn($this->mapping);

    $this->mappedObjectStorage = $this->getMock(SqlEntityStorageInterface::CLASS);

    $prophecy = $this->prophesize(EntityTypeManagerInterface::class);
    $prophecy->getStorage('salesforce_mapping')
      ->willReturn($this->mappingStorage);
    $prophecy->getStorage('salesforce_mapped_object')
      ->willReturn($this->mappedObjectStorage);
    $this->entityTypeManager = $prophecy->reveal();

    $container = new ContainerBuilder();
    $container->set('queue.salesforce_push', $this->queue);
    $container->set('salesforce.client', $this->client);
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('event_dispatcher', $this->eventDispatcher);
    $container->set('string_translation', $this->string_translation);
    $container->set('entity.manager', $this->entity_manager);
    \Drupal::setContainer($container);

    $this->handler = new Rest([], '', [], $this->queue, $this->client, $this->entityTypeManager, $this->eventDispatcher);

  }

  /**
   * @covers ::process
   * @expectedException \Drupal\Core\Queue\SuspendQueueException
   */
  public function testProcess() {
    $this->handler = $this->getMock(Rest::class, ['processItem'], [
      [],
      '',
      [],
      $this->queue,
      $this->client,
      $this->entityTypeManager,
      $this->eventDispatcher,
    ]);

    $this->client->expects($this->at(0))
      ->method('isAuthorized')
      ->willReturn(TRUE);

    // Test suspend queue if not authorized.
    $this->client->expects($this->at(1))
      ->method('isAuthorized')
      ->willReturn(FALSE);

    $this->handler->expects($this->once())
      ->method('processItem')
      ->willReturn(NULL);

    // Test delete item after successful processItem()
    $this->queue->expects($this->once())
      ->method('deleteItem')
      ->willReturn(NULL);

    $this->handler->process([(object) [1]]);
    $this->handler->process([(object) [2]]);
  }

  /**
   * @covers ::processItem
   */
  public function testProcessItemDeleteNoop() {
    $this->handler = $this->getMockBuilder(Rest::class)
      ->setConstructorArgs([
        [],
        '',
        [],
        $this->queue,
        $this->client,
        $this->entityTypeManager,
        $this->eventDispatcher,
      ])
      ->setMethods(['getMappedObject'])
      ->getMock();

    $mappedObject = $this->getMock(MappedObjectInterface::class);
    $mappedObject->expects($this->once())
      ->method('isNew')
      ->willReturn(TRUE);

    $this->handler->expects($this->once())
      ->method('getMappedObject')
      ->willReturn($mappedObject);

    $this->handler->processItem((object) [
      'op' => MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE,
      'mapped_object_id' => 'foo',
      'name' => 'bar',
    ]);
  }

  /**
   * @covers ::processItem
   */
  public function testProcessItemDelete() {
    // Test push delete for op == delete.
    $this->queueItem = (object) [
      'op' => MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE,
      'mapped_object_id' => 'foo',
      'name' => 'bar',
    ];

    $this->mappedObject = $this->getMock(MappedObjectInterface::class);

    $this->mappedObject->expects($this->once())
      ->method('pushDelete')
      ->willReturn(NULL);

    $this->handler = $this->getMock(Rest::class, ['getMappedObject'], [
      [],
      '',
      [],
      $this->queue,
      $this->client,
      $this->entityTypeManager,
      $this->eventDispatcher,
    ]);
    $this->handler->expects($this->once())
      ->method('getMappedObject')
      ->willReturn($this->mappedObject);

    // Test skip item on missing mapped object and op == delete
    // test push on op == insert / update
    // test throwing exception on drupal entity not found.
    $this->handler->processItem($this->queueItem);
  }

  /**
   * @covers ::processItem
   */
  public function testProcessItemPush() {
    // Test push on op == insert / update.
    $this->mappedObject = $this->getMock(MappedObjectInterface::class);
    $this->queueItem = (object) [
      'entity_id' => 'foo',
      'op' => NULL,
      'mapped_object_id' => NULL,
      'name' => NULL,
    ];
    $this->entity = $this->getMock(EntityInterface::class);
    $this->entityStorage = $this->getMock(SqlEntityStorageInterface::CLASS);
    $this->entityStorage->expects($this->once())
      ->method('load')
      ->willReturn($this->entity);

    $prophecy = $this->prophesize(EntityTypeManagerInterface::class);
    $prophecy->getStorage($this->entityType)
      ->willReturn($this->entityStorage);
    $prophecy->getStorage('salesforce_mapping')
      ->willReturn($this->mappingStorage);
    $prophecy->getStorage('salesforce_mapped_object')
      ->willReturn($this->mappedObjectStorage);
    $this->entityTypeManager = $prophecy->reveal();

    $this->mappedObject->expects($this->once())
      ->method('setDrupalEntity')
      ->willReturn($this->mappedObject);

    $this->mappedObject->expects($this->once())
      ->method('push')
      ->willReturn(NULL);

    $this->handler = $this->getMock(Rest::class, ['getMappedObject'], [
      [],
      '',
      [],
      $this->queue,
      $this->client,
      $this->entityTypeManager,
      $this->eventDispatcher,
    ]);
    $this->handler->expects($this->once())
      ->method('getMappedObject')
      ->willReturn($this->mappedObject);

    $this->handler->processItem($this->queueItem);

  }

  /**
   * @covers ::processItem
   *
   * @expectedException \Drupal\salesforce\EntityNotFoundException
   */
  public function testProcessItemEntityNotFound() {
    // Test throwing exception on drupal entity not found.
    $this->queueItem = (object) [
      'op' => '',
      'mapped_object_id' => 'foo',
      'name' => 'bar',
      'entity_id' => 'foo',
    ];

    $this->mappedObject = $this->getMock(MappedObjectInterface::class);
    $this->mappedObject->expects($this->any())
      ->method('isNew')
      ->willReturn(TRUE);

    $this->entityStorage = $this->getMock(SqlEntityStorageInterface::CLASS);
    $prophecy = $this->prophesize(EntityTypeManagerInterface::class);
    $prophecy->getStorage($this->entityType)
      ->willReturn($this->entityStorage);
    $prophecy->getStorage('salesforce_mapping')
      ->willReturn($this->mappingStorage);
    $prophecy->getStorage('salesforce_mapped_object')
      ->willReturn($this->mappedObjectStorage);
    $this->entityTypeManager = $prophecy->reveal();

    $this->entityStorage->expects($this->once())
      ->method('load')
      ->willReturn(NULL);

    $this->handler = $this->getMock(Rest::class, ['getMappedObject'], [
      [],
      '',
      [],
      $this->queue,
      $this->client,
      $this->entityTypeManager,
      $this->eventDispatcher,
    ]);
    $this->handler->expects($this->once())
      ->method('getMappedObject')
      ->willReturn($this->mappedObject);

    $this->handler->processItem($this->queueItem);
  }

}
