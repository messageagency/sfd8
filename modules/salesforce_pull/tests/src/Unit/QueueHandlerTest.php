<?php

namespace Drupal\Tests\salesforce_pull\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueDatabaseFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\SalesforceMappingStorage;
use Drupal\salesforce_pull\QueueHandler;
use Prophecy\Argument;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Test Object instantitation.
 *
 * @group salesforce_pull
 */
class QueueHandlerTest extends UnitTestCase {

  /**
   * Required modules.
   *
   * @var array
   */
  static public $modules = ['salesforce_pull'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $result = [
      'totalSize' => 1,
      'done' => TRUE,
      'records' => [],
    ];
    $this->sqrDone = new SelectQueryResult($result);

    $result['records'] = [
      [
        'Id' => '1234567890abcde',
        'attributes' => ['type' => 'dummy'],
        'name' => 'Example',
      ],
    ];
    $this->sqr = new SelectQueryResult($result);

    $soql = new SelectQuery('dummy');
    $soql->fields = ['Id'];
    $soql->addCondition('LastModifiedDate', '1970-1-1T00:00:00Z', '>');

    $prophecy = $this->prophesize(RestClientInterface::CLASS);
    $prophecy->query(Argument::any())
      ->willReturn($this->sqr);
    $prophecy->queryMore(Argument::any())
      ->willReturn($this->sqrDone);
    $this->sfapi = $prophecy->reveal();

    $this->mapping = $this->getMockBuilder(SalesforceMappingInterface::CLASS)->getMock();
    $this->mapping->expects($this->any())
      ->method('__get')
      ->with($this->equalTo('id'))
      ->willReturn(1);
    $this->mapping->expects($this->any())
      ->method('getSalesforceObjectType')
      ->willReturn('default');
    $this->mapping->expects($this->any())
      ->method('getPullFieldsArray')
      ->willReturn(['Name' => 'Name', 'Account Number' => 'Account Number']);
    $this->mapping->expects($this->any())
      ->method('getNextPullTime')
      ->willReturn(0);
    $this->mapping->method('getPullQuery')
      ->willReturn($soql);

    $prophecy = $this->prophesize(QueueInterface::CLASS);
    $prophecy->createItem()->willReturn(1);
    $prophecy->numberOfItems()->willReturn(2);
    $this->queue = $prophecy->reveal();

    $prophecy = $this->prophesize(QueueDatabaseFactory::CLASS);
    $prophecy->get(Argument::any())->willReturn($this->queue);
    $this->queue_factory = $prophecy->reveal();

    // Mock mapping ConfigEntityStorage object.
    $prophecy = $this->prophesize(SalesforceMappingStorage::CLASS);
    $prophecy->loadCronPullMappings(Argument::any())->willReturn([$this->mapping]);
    $this->mappingStorage = $prophecy->reveal();

    // Mock EntityTypeManagerInterface.
    $prophecy = $this->prophesize(EntityTypeManagerInterface::CLASS);
    $prophecy->getStorage('salesforce_mapping')->willReturn($this->mappingStorage);
    $this->etm = $prophecy->reveal();

    // Mock config.
    $prophecy = $this->prophesize(Config::CLASS);
    $prophecy->get('pull_max_queue_size', Argument::any())->willReturn(QueueHandler::PULL_MAX_QUEUE_SIZE);
    $config = $prophecy->reveal();

    $prophecy = $this->prophesize(ConfigFactoryInterface::CLASS);
    $prophecy->get('salesforce.settings')->willReturn($config);
    $this->configFactory = $prophecy->reveal();

    // Mock state.
    $prophecy = $this->prophesize(StateInterface::CLASS);
    $prophecy->get('salesforce.mapping_pull_info', Argument::any())->willReturn([1 => ['last_pull_timestamp' => '0']]);
    $prophecy->set('salesforce.mapping_pull_info', Argument::any())->willReturn(NULL);
    $this->state = $prophecy->reveal();

    // Mock event dispatcher.
    $prophecy = $this->prophesize(EventDispatcherInterface::CLASS);
    $prophecy->dispatch(Argument::any(), Argument::any())->willReturn();
    $this->ed = $prophecy->reveal();

    $this->time = $this->getMockBuilder(TimeInterface::CLASS)->getMock();

    $this->qh = $this->getMockBuilder(QueueHandler::CLASS)
      ->setMethods(['parseUrl'])
      ->setConstructorArgs([
        $this->sfapi,
        $this->etm,
        $this->queue_factory,
        $this->configFactory,
        $this->ed,
        $this->time,
      ])
      ->getMock();
    $this->qh->expects($this->any())
      ->method('parseUrl')
      ->willReturn('https://example.salesforce.com');
  }

  /**
   * Test object instantiation.
   */
  public function testObject() {
    $this->assertTrue($this->qh instanceof QueueHandler);
  }

  /**
   * Test handler operation, good data.
   */
  public function testGetUpdatedRecords() {
    $result = $this->qh->getUpdatedRecords();
    $this->assertTrue($result);
  }

  /**
   * Test handler operation, too many queue items.
   */
  public function testTooManyQueueItems() {
    // Initialize with queue size > 100000 (default)
    $prophecy = $this->prophesize(QueueInterface::CLASS);
    $prophecy->createItem()->willReturn(1);
    $prophecy->numberOfItems()->willReturn(QueueHandler::PULL_MAX_QUEUE_SIZE + 1);
    $this->queue = $prophecy->reveal();

    $prophecy = $this->prophesize(QueueDatabaseFactory::CLASS);
    $prophecy->get(Argument::any())->willReturn($this->queue);
    $this->queue_factory = $prophecy->reveal();

    $this->qh = $this->getMockBuilder(QueueHandler::CLASS)
      ->setMethods(['parseUrl'])
      ->setConstructorArgs([
        $this->sfapi,
        $this->etm,
        $this->queue_factory,
        $this->configFactory,
        $this->ed,
        $this->time,
      ])
      ->getMock();
    $this->qh->expects($this->any())
      ->method('parseUrl')
      ->willReturn('https://example.salesforce.com');
    $result = $this->qh->getUpdatedRecords();
    $this->assertFalse($result);
  }

}
