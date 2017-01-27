<?php
namespace Drupal\Tests\salesforce\salesforce_pull;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityStorageBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_pull\Plugin\QueueWorker\PullBase;
use Drupal\salesforce_pull\PullQueueItem;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce\SObject;
use Drupal\Tests\UnitTestCase;
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

    // mock mapping object
    $this->mapping = $this->getMockBuilder(SalesforceMappingInterface::CLASS)
      ->setMethods(['__construct', '__get', 'checkTriggers', 'getDrupalEntityType', 'getDrupalBundle'])
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

    // mock mapped object
    $prophecy = $this->prophesize(MappedObjectInterface::CLASS);
    // @TODO: make MappedObjects testable and thus mockable better here
    //$prophecy->get('entity_type_id')->willReturn($this->entityTypeId);
    //$prophecy->get('entity_id')->willReturn($this->entityId);
    $this->mappedObject = $prophecy->reveal();


    // mock mapping ConfigEntityStorage object
    $prophecy = $this->prophesize(ConfigEntityStorage::CLASS);
    $prophecy->load(Argument::any())->willReturn($this->mapping);
    $this->configStorage = $prophecy->reveal();

    // mock mapped object EntityStorage object
    $prophecy = $this->prophesize(EntityStorageBase::CLASS);
    $prophecy->loadByProperties(Argument::any())->willReturn([$this->mappedObject]);
    $this->entityStorage = $prophecy->reveal();

    // mock new Drupal entity EntityStorage object
    $prophecy = $this->prophesize(EntityStorageBase::CLASS);
    $prophecy->create(Argument::any())->willReturn();
    $this->newEntityStorage = $prophecy->reveal();

    // mock EntityType Definition
    $prophecy = $this->prophesize(EntityTypeInterface::CLASS);
    $prophecy->getKeys(Argument::any())->willReturn([
      'bundle' => 'test',
    ]);
    $this->entityDefinition = $prophecy->reveal();

    // mock EntityTypeManagerInterface
    $prophecy = $this->prophesize(EntityTypeManagerInterface::CLASS);
    $prophecy->getStorage('salesforce_mapping')->willReturn($this->configStorage);
    $prophecy->getStorage('salesforce_mapped_object')->willReturn($this->entityStorage);
    $prophecy->getStorage('test')->willReturn($this->newEntityStorage); $prophecy->getDefinition('test')->willReturn($this->entityDefinition);
    $this->etm = $prophecy->reveal();

    // SelectQueryResult for rest client call
    $result = [
      'totalSize' => 1,
      'done' => true,
      'records' => [
        [
          'Id' => '1234567890abcde',
          'attributes' => ['type' => 'dummy',],
          'name' => 'Example',
        ],
      ]
    ];
    $this->sqr = new SelectQueryResult($result);

    // mock rest cient
    $prophecy = $this->prophesize(RestClient::CLASS);
    $prophecy->query(Argument::any())
      ->willReturn($this->sqr);
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

    $this->pullWorker = $this->getMockBuilder(PullBase::CLASS)
      ->setConstructorArgs([$this->etm, $this->sfapi, $this->mh, $this->lf])
      ->getMockForAbstractClass();
  }

  /**
   * Test object instantiation
   */
  public function testObject() {
    $this->assertTrue($this->pullWorker instanceof PullBase);
  }

  /**
   * Test handler operation, good data
   */

  public function testProcessItem() {
    $sobject = new SObject(['id' => '1234567890abcde', 'attributes' => ['type' => 'dummy',]]);
    $item = new PullQueueItem($sobject, $this->mapping);

    $this->pullWorker->ProcessItem($item);
    // @TODO fix MappedObject so it will be mockable and used as expected in tests here
    $this->assertEquals('update', $this->pullWorker->getDone());
  }
}
