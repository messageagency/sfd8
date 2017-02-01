<?php
namespace Drupal\Tests\salesforce\salesforce_pull;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\State\StateInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_pull\QueueHandler;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce\SObject;
use Drupal\Tests\salesforce\salesforce_pull\TestQueueHandler;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
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

    $prophecy = $this->prophesize(RestClient::CLASS);
    $prophecy->query(Argument::any())
      ->willReturn($this->sqr);
    $this->sfapi = $prophecy->reveal();

    $this->mapping = $this->getMockBuilder(SalesforceMappingInterface::CLASS)
      ->setMethods(['__construct', '__get', 'get', 'getSalesforceObjectType', 'getPullFieldsArray'])
      ->getMock();
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

    $prophecy = $this->prophesize(QueueInterface::CLASS);
    $prophecy->createItem()->willReturn(1);
    $prophecy->numberOfItems()->willReturn(2);
    $this->queue = $prophecy->reveal();

    // mock state
    $prophecy = $this->prophesize(StateInterface::CLASS);
    $prophecy->get('salesforce_pull_last_sync_default', Argument::any())->willReturn('1485787434');
    $prophecy->get('salesforce_pull_max_queue_size', Argument::any())->willReturn('100000');
    $prophecy->set('salesforce_pull_last_sync_default', Argument::any())->willReturn(null);
    $this->state = $prophecy->reveal();

    // mock logger
    $prophecy = $this->prophesize(LoggerInterface::CLASS);
    $prophecy->log(Argument::any(), Argument::any(), Argument::any())->willReturn(null);
    $this->logger = $prophecy->reveal();

    // mock event dispatcher
    $prophecy = $this->prophesize(ContainerAwareEventDispatcher::CLASS);
    $this->ed = $prophecy->reveal();

    // mock server
    $prophecy = $this->prophesize(ServerBag::CLASS);
    $prophecy->get(Argument::any())->willReturn('1485787434');
    $this->server = $prophecy->reveal();

    // mock request
    $prophecy = $this->prophesize(Request::CLASS);
    $prophecy->server = $this->server;
    $this->request = $prophecy->reveal();

    $this->qh = $this->getMockBuilder(QueueHandler::CLASS)
      ->setMethods(['parseUrl'])
      ->setConstructorArgs([$this->sfapi, [$this->mapping], $this->queue, $this->state, $this->logger, $this->ed, $this->request])
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
    $prophecy->numberOfItems()->willReturn(100001);
    $this->queue = $prophecy->reveal();

    $this->qh = $this->getMockBuilder(QueueHandler::CLASS)
      ->setMethods(['parseUrl'])
      ->setConstructorArgs([$this->sfapi, [$this->mapping], $this->queue, $this->state, $this->logger, $this->ed, $this->request])
      ->getMock();
    $this->qh->expects($this->any())
      ->method('parseUrl')
      ->willReturn('https://example.salesforce.com');
    $result = $this->qh->getUpdatedRecords();
    $this->assertFalse($result);
  }

}
