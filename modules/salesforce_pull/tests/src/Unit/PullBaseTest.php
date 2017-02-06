<?php
namespace Drupal\Tests\salesforce_pull\Unit;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\Translator\TranslationInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce\SObject;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce_mapping\Entity\MappedObject;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\salesforce_pull\Plugin\QueueWorker\PullBase;
use Drupal\salesforce_pull\PullQueueItem;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;

/**
 * Test Object instantitation
 *
 * @group salesforce_pull
 */

class PullBaseTest extends UnitTestCase {
  static $modules = ['salesforce_pull'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->entityTypeId = $this->entityId = new \stdClass();
    $this->entityTypeId->value = 'test';
    $this->entityId->value = '1';
    $this->salesforce_id = '1234567890abcde';

    // mock SFID
    $prophecy = $this->prophesize(SFID::CLASS);
    $prophecy
      ->__toString(Argument::any())
      ->willReturn($this->salesforce_id);
    $this->sfid = $prophecy->reveal();

    // mock content entity
    $prophecy = $this->prophesize(ContentEntityInterface::CLASS);
    $prophecy->label(Argument::any())->willReturn('test');
    $prophecy->id(Argument::any())->willReturn(1);
    $this->entity = $prophecy->reveal();

    // mock mapping object
    $this->mapping = $this->getMockBuilder(SalesforceMappingInterface::CLASS)
      ->setMethods(['__construct', '__get', 'checkTriggers', 'getDrupalEntityType', 'getDrupalBundle', 'getFieldMappings', 'getSalesforceObjectType'])
      ->getMock();
    $this->mapping->expects($this->any())
      ->method('__get')
      ->with($this->equalTo('id'))
      ->willReturn(1);
    $this->mapping->expects($this->any())
      ->method('checkTriggers')
      ->willReturn(true);
    $this->mapping->method('getDrupalEntityType')
      ->willReturn('test');
    $this->mapping->method('getDrupalBundle')
      ->willReturn('test');
    $this->mapping->method('getSalesforceObjectType')
      ->willReturn('test');
    // @TODO testing a mapping with no fields is of questionable value:
    $this->mapping->method('getFieldMappings')
      ->willReturn([]);

    // mock mapped object
    $prophecy = $this->prophesize(MappedObject::CLASS);
    // @TODO: make MappedObjects testable and thus mockable better here
    $prophecy
      ->get('entity_type_id')
      ->willReturn($this->entityTypeId);
    $prophecy
      ->get('entity_id')
      ->willReturn($this->entityId);
    $prophecy
      ->get('salesforce_mapping')
      ->willReturn($this->mapping);
    $prophecy
      ->get('salesforce_id')
      ->willReturn($this->salesforce_id);
    $prophecy
      ->sfid(Argument::any())
      ->willReturn($this->salesforce_id);
    $prophecy
      ->setDrupalEntity(Argument::any())
      ->willReturn($prophecy->reveal());
    $prophecy
      ->setSalesforceRecord(Argument::any())
      ->willReturn($prophecy->reveal());
    $prophecy
      ->getMappedEntity(Argument::any())
      ->willReturn($this->entity);
    $prophecy
      ->getMapping(Argument::any())
      ->willReturn($this->mapping);
    $prophecy
      ->pull(Argument::any())
      ->willReturn(NULL);
    $prophecy
      ->id(Argument::any())
      ->willReturn($this->salesforce_id);
    $prophecy
      ->label(Argument::any())
      ->willReturn('test');
    $this->mappedObject = $prophecy->reveal();

    // mock mapping ConfigEntityStorage object
    $prophecy = $this->prophesize(ConfigEntityStorage::CLASS);
    $prophecy->load(Argument::any())->willReturn($this->mapping);
    $this->configStorage = $prophecy->reveal();

    // mock mapped object EntityStorage object
    $prophecy = $this->prophesize(EntityStorageBase::CLASS);
    $prophecy
      ->loadByProperties(Argument::any())
      ->willReturn([$this->mappedObject]);
    $this->entityStorage = $prophecy->reveal();

    // mock new Drupal entity EntityStorage object
    $prophecy = $this->prophesize(EntityStorageBase::CLASS);
    $prophecy
      ->loadByProperties(Argument::any())
      ->willReturn([]);
    $prophecy
      ->create(Argument::any())
      ->willReturn($this->mappedObject);
    $this->newEntityStorage = $prophecy->reveal();

    // mock EntityType Definition
    $prophecy = $this->prophesize(EntityTypeInterface::CLASS);
    $prophecy->getKeys(Argument::any())->willReturn([
      'bundle' => 'test',
    ]);
    $prophecy->id = 'test';
    $this->entityDefinition = $prophecy->reveal();

    // mock EntityTypeManagerInterface
    $prophecy = $this->prophesize(EntityTypeManagerInterface::CLASS);
    $prophecy
      ->getStorage('salesforce_mapping')
      ->willReturn($this->configStorage);
    $prophecy
      ->getStorage('salesforce_mapped_object')
      ->willReturn($this->entityStorage);
    $prophecy
      ->getStorage('test')
      ->willReturn($this->newEntityStorage);
    $prophecy
      ->getDefinition('test')
      ->willReturn($this->entityDefinition);
    $this->etm = $prophecy->reveal();

    // SelectQueryResult for rest client call
    $result = [
      'totalSize' => 1,
      'done' => true,
      'records' => [
        [
          'Id' => $this->salesforce_id,
          'attributes' => ['type' => 'test',],
          'name' => 'Example',
        ],
      ]
    ];
    $this->sqr = new SelectQueryResult($result);

    // mock rest cient
    $prophecy = $this->prophesize(RestClient::CLASS);
    $prophecy
      ->query(Argument::any())
      ->willReturn($this->sqr);
    $prophecy
      ->objectUpdate(Argument::any(), Argument::any(), Argument::any())
      ->willReturn(NULL);
    $prophecy
      ->objectCreate(Argument::any())
      ->willReturn($this->sfid);
    $this->sfapi = $prophecy->reveal();

    // mock module handler
    $prophecy = $this->prophesize(ModuleHandlerInterface::CLASS);
    $this->mh = $prophecy->reveal();

    // mock logger
    $prophecy = $this->prophesize(LoggerInterface::CLASS);
    $prophecy->log(Argument::any(),Argument::any(),Argument::any())->willReturn(null);
    $this->logger = $prophecy->reveal();

    // mock logger factory
    $prophecy = $this->prophesize(LoggerChannelFactoryInterface::CLASS);
    $prophecy->get(Argument::any())->willReturn($this->logger);
    $this->lf = $prophecy->reveal();

    // mock event dispatcher
    $prophecy = $this->prophesize(ContainerAwareEventDispatcher::CLASS);
    $prophecy->dispatch(Argument::any(),Argument::any())->willReturn(NULL);
    $this->ed = $prophecy->reveal();

    $this->pullWorker = $this->getMockBuilder(PullBase::CLASS)
      ->setConstructorArgs([
        $this->etm,
        $this->sfapi,
        $this->mh,
        $this->lf,
        $this->ed
      ])
      ->getMockForAbstractClass();
  }

  /**
   * Test object instantiation
   */
  public function testObject() {
    $this->assertTrue($this->pullWorker instanceof PullBase);
  }

  /**
   * Test handler operation, update with good data
   */
  public function testProcessItemUpdate() {
    $sobject = new SObject(['id' => $this->salesforce_id, 'attributes' => ['type' => 'test',]]);
    $item = new PullQueueItem($sobject, $this->mapping);
    $this->assertEquals(MappingConstants::SALESFORCE_MAPPING_SYNC_SF_UPDATE, $this->pullWorker->processItem($item));
  }

  /**
   * Test handler operation, create with good data
   */
  public function testProcessItemCreate() {
    // mock EntityTypeManagerInterface
    $prophecy = $this->prophesize(EntityTypeManagerInterface::CLASS);
    $prophecy
      ->getStorage('salesforce_mapping')
      ->willReturn($this->configStorage);
    $prophecy
      ->getStorage('salesforce_mapped_object')
      ->willReturn($this->newEntityStorage);
    $prophecy
      ->getStorage('test')
      ->willReturn($this->newEntityStorage);
    $prophecy
      ->getDefinition('test')
      ->willReturn($this->entityDefinition);
    $this->etm = $prophecy->reveal();

    $this->pullWorker = $this->getMockBuilder(PullBase::CLASS)
      ->setConstructorArgs([$this->etm, $this->sfapi, $this->mh, $this->lf, $this->ed])
      ->getMockForAbstractClass();

    $sobject = new SObject(['id' => $this->salesforce_id, 'attributes' => ['type' => 'test',]]);
    $item = new PullQueueItem($sobject, $this->mapping);

    $this->assertEquals(MappingConstants::SALESFORCE_MAPPING_SYNC_SF_CREATE, $this->pullWorker->processItem($item));
    $this->assertEmpty($this->etm
      ->getStorage('salesforce_mapped_object')
      ->loadByProperties(['name' => 'test_test'])
    );
  }
}
