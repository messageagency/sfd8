<?php
namespace Drupal\Tests\salesforce_pull\Unit;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueDatabaseFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\State\StateInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\SalesforceMappingStorage;
use Drupal\salesforce_pull\QueueHandler;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\SelectQueryResult;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\ServerBag;

/**
 * Test Object instantitation
 *
 * @group salesforce_pull
 */

class QueueHandlerTest extends UnitTestCase {
  static $modules = ['salesforce_pull'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
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

    $prophecy = $this->prophesize(RestClientInterface::CLASS);
    $prophecy->query(Argument::any())
      ->willReturn($this->sqr);
    $this->sfapi = $prophecy->reveal();

    $this->mapping = $this->getMock(SalesforceMappingInterface::CLASS);
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

    $prophecy = $this->prophesize(QueueInterface::CLASS);
    $prophecy->createItem()->willReturn(1);
    $prophecy->numberOfItems()->willReturn(2);
    $this->queue = $prophecy->reveal();

    $prophecy = $this->prophesize(QueueDatabaseFactory::CLASS);
    $prophecy->get(Argument::any())->willReturn($this->queue);
    $this->queue_factory = $prophecy->reveal();

    // Mock mapping ConfigEntityStorage object.
    $prophecy = $this->prophesize(SalesforceMappingStorage::CLASS);
    $prophecy->loadPullMappings(Argument::any())->willReturn([$this->mapping]);
    $this->mappingStorage = $prophecy->reveal();

    // Mock EntityTypeManagerInterface.
    $prophecy = $this->prophesize(EntityTypeManagerInterface::CLASS);
    $prophecy->getStorage('salesforce_mapping')->willReturn($this->mappingStorage);
    $this->etm = $prophecy->reveal();


    // mock state
    $prophecy = $this->prophesize(StateInterface::CLASS);
    $prophecy->get('salesforce.sobject_pull_info', Argument::any())->willReturn(['default' => ['last_pull_timestamp' => '0']]);
    $prophecy->get('salesforce.pull_max_queue_size', Argument::any())->willReturn(QueueHandler::PULL_MAX_QUEUE_SIZE);
    $prophecy->set('salesforce.sobject_pull_info', Argument::any())->willReturn(null);
    $this->state = $prophecy->reveal();

    // mock event dispatcher
    $prophecy = $this->prophesize(EventDispatcherInterface::CLASS);
    $prophecy->dispatch(Argument::any(), Argument::any())->willReturn();
    $this->ed = $prophecy->reveal();

    // mock server
    $prophecy = $this->prophesize(ServerBag::CLASS);
    $prophecy->get(Argument::any())->willReturn('1485787434');
    $this->server = $prophecy->reveal();

    // mock request
    $request = $this->prophesize(Request::CLASS);
    $request->server = $this->server;

    // mock request stack
    $prophecy = $this->prophesize(RequestStack::CLASS);
    $prophecy->getCurrentRequest()->willReturn($request->reveal());
    $this->request_stack = $prophecy->reveal();

    $this->qh = $this->getMockBuilder(QueueHandler::CLASS)
      ->setMethods(['parseUrl'])
      ->setConstructorArgs([$this->sfapi, $this->etm, $this->queue_factory, $this->state, $this->ed, $this->request_stack])
      ->getMock();
    $this->qh->expects($this->any())
      ->method('parseUrl')
      ->willReturn('https://example.salesforce.com');
  }

  /**
   * Test object instantiation
   */
  public function testObject() {
    $this->assertTrue($this->qh instanceof QueueHandler);
  }

  /**
   * Test handler operation, good data
   */
  public function testGetUpdatedRecords() {
    $result = $this->qh->getUpdatedRecords();
    $this->assertTrue($result);
  }

  /**
   * Test handler operation, too many queue items
   */
  public function testTooManyQueueItems() {
    // initialize with queue size > 100000 (default)
    $prophecy = $this->prophesize(QueueInterface::CLASS);
    $prophecy->createItem()->willReturn(1);
    $prophecy->numberOfItems()->willReturn(QueueHandler::PULL_MAX_QUEUE_SIZE + 1);
    $this->queue = $prophecy->reveal();

    $prophecy = $this->prophesize(QueueDatabaseFactory::CLASS);
    $prophecy->get(Argument::any())->willReturn($this->queue);
    $this->queue_factory = $prophecy->reveal();

    $this->qh = $this->getMockBuilder(QueueHandler::CLASS)
      ->setMethods(['parseUrl'])
      ->setConstructorArgs([$this->sfapi, $this->etm, $this->queue_factory, $this->state, $this->ed, $this->request_stack])
      ->getMock();
    $this->qh->expects($this->any())
      ->method('parseUrl')
      ->willReturn('https://example.salesforce.com');
    $result = $this->qh->getUpdatedRecords();
    $this->assertFalse($result);
  }

}
