<?php

namespace Drupal\Tests\salesforce_pull\Unit;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\salesforce_mapping\Event\SalesforcePullEvent;
use Drupal\Tests\UnitTestCase;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SObject;
use Drupal\salesforce\SelectQueryResult;

use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\salesforce_pull\Plugin\QueueWorker\PullBase;
use Drupal\salesforce_pull\PullQueueItem;
use Prophecy\Argument;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Test Object instantitation.
 *
 * @group salesforce_pull
 */
class PullBaseTest extends UnitTestCase {

  /**
   * Required modules.
   *
   * @var array
   */
  public static $modules = ['salesforce_pull'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->salesforce_id = '1234567890abcde';

    // Mock SFID.
    $prophecy = $this->prophesize(SFID::CLASS);
    $prophecy
      ->__toString(Argument::any())
      ->willReturn($this->salesforce_id);
    $this->sfid = $prophecy->reveal();

    // Mock StringItem for mock Entity.
    $changed_value = $this->getMockBuilder(StringItem::CLASS)
      ->setMethods(['__get'])
      ->disableOriginalConstructor()
      // ->setConstructorArgs([$ddi,null,null])
      ->getMock();
    $changed_value->expects($this->any())
      ->method('__get')
      ->with($this->equalTo('value'))
      ->willReturn('999999');

    // Mock content entity.
    $this->entity = $this->getMockBuilder(ContentEntityBase::CLASS)
      ->setMethods(['__construct', '__get', '__set', 'label', 'id', '__isset'])
      ->disableOriginalConstructor()
      ->getMock();
    $this->entity->expects($this->any())
      ->method('__get')
      ->with($this->equalTo('changed'))
      ->willReturn($changed_value);
    $this->entity->expects($this->any())
      ->method('__set')
      ->with($this->equalTo('salesforce_pull'));
    $this->entity->expects($this->any())
      ->method('__isset')
      ->with($this->equalTo('changed'))
      ->willReturn(TRUE);

    // Mock mapping object.
    $this->mapping = $this->getMockBuilder(SalesforceMappingInterface::CLASS)->getMock();
    $this->mapping->expects($this->any())
      ->method('__get')
      ->with($this->equalTo('id'))
      ->willReturn(1);
    $this->mapping->expects($this->any())
      ->method('checkTriggers')
      ->willReturn(TRUE);
    $this->mapping->method('getPullTriggerDate')
      ->willReturn('pull_trigger_date');
    $this->mapping->method('getDrupalEntityType')
      ->willReturn('test');
    $this->mapping->method('getDrupalBundle')
      ->willReturn('test');
    $this->mapping->method('getSalesforceObjectType')
      ->willReturn('test');
    // @TODO testing a mapping with no fields is of questionable value:
    $this->mapping->method('getFieldMappings')
      ->willReturn([]);

    // Mock mapped object.
    $this->mappedObject = $this->getMockBuilder(MappedObjectInterface::CLASS)->getMock();
    $this->mappedObject->expects($this->any())
      ->method('getChanged')
      ->willReturn('1486490500');
    $this->mappedObject->expects($this->any())
      ->method('setDrupalEntity')
      ->willReturn($this->mappedObject);
    $this->mappedObject->expects($this->any())
      ->method('setSalesforceRecord')
      ->willReturn($this->mappedObject);
    $this->mappedObject->expects($this->any())
      ->method('pull')
      ->willReturn($this->mappedObject);
    $this->mappedObject->expects($this->any())
      ->method('sfid')
      ->willReturn($this->salesforce_id);
    $this->mappedObject->expects($this->any())
      ->method('getMappedEntity')
      ->willReturn($this->entity);

    // Mock mapping ConfigEntityStorage object.
    $prophecy = $this->prophesize(ConfigEntityStorage::CLASS);
    $prophecy->load(Argument::any())->willReturn($this->mapping);
    $this->salesforceMappingStorage = $prophecy->reveal();

    // Mock mapped object EntityStorage object.
    $prophecy = $this->prophesize(EntityStorageBase::CLASS);
    $prophecy
      ->loadByProperties(Argument::any())
      ->willReturn([$this->mappedObject]);
    $prophecy
      ->create(Argument::type('array'))
      ->willReturn($this->mappedObject);
    $this->mappedObjectStorage = $prophecy->reveal();

    // Mock new Drupal entity EntityStorage object.
    $prophecy = $this->prophesize(EntityStorageBase::CLASS);
    $prophecy
      ->load(Argument::any())
      ->willReturn($this->entity);
    $prophecy
      ->create(Argument::type('array'))
      ->willReturn($this->entity);
    $this->drupalEntityStorage = $prophecy->reveal();

    // Mock EntityType Definition.
    $prophecy = $this->prophesize(EntityTypeInterface::CLASS);
    $prophecy->getKeys(Argument::any())->willReturn([
      'bundle' => 'test',
    ]);
    $prophecy->id = 'test';
    $this->entityDefinition = $prophecy->reveal();

    // Mock EntityTypeManagerInterface.
    $prophecy = $this->prophesize(EntityTypeManagerInterface::CLASS);
    $prophecy
      ->getStorage('salesforce_mapping')
      ->willReturn($this->salesforceMappingStorage);
    $prophecy
      ->getStorage('salesforce_mapped_object')
      ->willReturn($this->mappedObjectStorage);
    $prophecy
      ->getStorage('test')
      ->willReturn($this->drupalEntityStorage);
    $prophecy
      ->getDefinition('test')
      ->willReturn($this->entityDefinition);
    $this->etm = $prophecy->reveal();

    // SelectQueryResult for rest client call.
    $result = [
      'totalSize' => 1,
      'done' => TRUE,
      'records' => [
        [
          'Id' => $this->salesforce_id,
          'attributes' => ['type' => 'test'],
          'name' => 'Example',
        ],
      ],
    ];
    $this->sqr = new SelectQueryResult($result);

    // Mock rest cient.
    $this->sfapi = $this->getMockBuilder(RestClientInterface::CLASS)->getMock();
    $this->sfapi
      ->expects($this->any())
      ->method('query')
      ->willReturn($this->sqr);
    $this->sfapi
      ->expects($this->any())
      ->method('objectUpdate')
      ->willReturn(NULL);
    $this->sfapi
      ->expects($this->any())
      ->method('objectCreate')
      ->willReturn($this->sfid);

    // Mock event dispatcher.
    $this->ed = $this->getMockBuilder('\Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
    $this->ed
      ->expects($this->any())
      ->method('dispatch')
      ->willReturn(new SalesforcePullEvent($this->mappedObject, MappingConstants::SALESFORCE_MAPPING_SYNC_SF_UPDATE));

    $container = new ContainerBuilder();
    $container->set('salesforce.client', $this->sfapi);
    $container->set('event_dispatcher', $this->ed);
    $container->set('entity_type.manager', $this->etm);
    \Drupal::setContainer($container);

    $this->pullWorker = $this
      ->getMockBuilder(PullBase::CLASS)
      ->setMethods(['getMappedEntity'])
      ->setConstructorArgs([
        $this->etm,
        $this->sfapi,
        $this->ed,
      ])
      ->getMock();
    $this->pullWorker->expects($this->any())
      ->method('getMappedEntity')
      ->willReturn($this->entity);
  }

  /**
   * Test object instantiation.
   */
  public function testObject() {
    $this->assertTrue($this->pullWorker instanceof PullBase);
  }

  /**
   * Test handler operation, update with good data.
   */
  public function testProcessItemUpdate() {
    $sobject = new SObject([
      'id' => $this->salesforce_id,
      'attributes' => ['type' => 'test'],
      'pull_trigger_date' => 'now',
    ]);
    $item = new PullQueueItem($sobject, $this->mapping);
    $this->assertEquals(MappingConstants::SALESFORCE_MAPPING_SYNC_SF_UPDATE, $this->pullWorker->processItem($item));
  }

  /**
   * Test handler operation, create with good data.
   */
  public function testProcessItemCreate() {
    // Mock mapped object EntityStorage object.
    $prophecy = $this->prophesize(EntityStorageBase::CLASS);
    $prophecy
      ->loadByProperties(Argument::any())
      ->willReturn([]);
    $prophecy
      ->create(Argument::type('array'))
      ->willReturn($this->mappedObject);
    $entityStorage = $prophecy->reveal();

    // Mock EntityTypeManagerInterface
    // (with special MappedObjectStorage mock above)
    $prophecy = $this->prophesize(EntityTypeManagerInterface::CLASS);
    $prophecy
      ->getStorage('salesforce_mapping')
      ->willReturn($this->salesforceMappingStorage);
    $prophecy
      ->getStorage('salesforce_mapped_object')
      ->willReturn($entityStorage);
    $prophecy
      ->getStorage('test')
      ->willReturn($this->drupalEntityStorage);
    $prophecy
      ->getDefinition('test')
      ->willReturn($this->entityDefinition);
    $this->etm = $prophecy->reveal();

    $this->pullWorker = $this->getMockBuilder(PullBase::CLASS)
      ->setConstructorArgs([$this->etm, $this->sfapi, $this->ed])
      ->setMethods(['salesforcePullEvent'])
      ->getMockForAbstractClass();
    $this->pullWorker->method('salesforcePullEvent')
      ->willReturn(NULL);

    $sobject = new SObject(['id' => $this->salesforce_id, 'attributes' => ['type' => 'test']]);
    $item = new PullQueueItem($sobject, $this->mapping);

    $this->assertEquals(MappingConstants::SALESFORCE_MAPPING_SYNC_SF_CREATE, $this->pullWorker->processItem($item));
    $this->assertEmpty($this->etm
      ->getStorage('salesforce_mapped_object')
      ->loadByProperties(['name' => 'test_test'])
    );
  }

}
