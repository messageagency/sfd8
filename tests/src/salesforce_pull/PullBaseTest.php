<?php
namespace Drupal\Tests\salesforce\salesforce_pull;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\salesforce\salesforce_pull\TestPullBase;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityStorageBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Component\Plugin\Definition\PluginDefinitionInterface;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce\SObject;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_pull\PullQueueItem;
use Prophecy\Argument;


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

    // mock hander for getStorage calls


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
    $this->mappedObject = $prophecy->reveal();

    // mock ConfigEntityStorage object
    $prophecy = $this->prophesize(ConfigEntityStorage::CLASS);
    $prophecy->load(Argument::any())->willReturn($this->mapping);
    $this->configStorage = $prophecy->reveal();

    // mock EntityStorage object
    $prophecy = $this->prophesize(EntityStorageBase::CLASS);
    $prophecy->loadByProperties(Argument::any())->willReturn([$this->mappedObject]);
    $this->entityStorage = $prophecy->reveal();

    // mock Definition
    $prophecy = $this->prophesize(PluginDefinitionInterface::CLASS);
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

    $this->pullWorker = new TestPullBase($this->etm, $this->sfapi, $this->mh);
  }

  /**
   * Test object instantiation
   */
  public function testObject() {
    $this->assertTrue($this->pullWorker instanceof TestPullBase);
  }

  /**
   * Test handler operation, good data
   */

  public function testProcessItem() {
    $sobject = new SObject(['id' => '1234567890abcde', 'attributes' => ['type' => 'dummy',]]);
    $item = new PullQueueItem($sobject, $this->mapping);

    $result = $this->pullWorker->ProcessItem($item);
    $this->assertTrue($result);
  }

  /**
   * Test handler operation, too many queue items
   */
   /*
  public function testTooManyQueueItems() {
    // initialize with queue size > 100000 (default)
    $prophecy = $this->prophesize(QueueInterface::CLASS);
    $prophecy->createItem()->willReturn(1);
    $prophecy->numberOfItems()->willReturn(100001);
    $this->queue = $prophecy->reveal();

    $this->qh = TestQueueHandler::create($this->sfapi, [$this->mapping], $this->queue);
    $result = $this->qh->getUpdatedRecords();
    $this->assertFalse($result);
  }
  */

}
