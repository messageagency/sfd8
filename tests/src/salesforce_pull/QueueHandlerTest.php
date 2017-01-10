<?php
namespace Drupal\Tests\salesforce\salesforce_pull;

use Drupal\Tests\UnitTestCase;
use Drupal\Tests\salesforce\salesforce_pull\TestQueueHandler;
//use Drupal\salesforce_pull\QueueHandler;
use Drupal\salesforce\SObject;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce\Rest\restClient;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\Core\Queue\QueueInterface;
//use Drupal\salesforce_pull\PullQueueItem;
use Prophecy\Argument;


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

    $this->qh = TestQueueHandler::create($this->sfapi, [$this->mapping], $this->queue);
  }

  /**
   * Test object instantiation
   */
  public function testObject() {
    $this->assertTrue($this->qh instanceof TestQueueHandler);
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

    $this->qh = TestQueueHandler::create($this->sfapi, [$this->mapping], $this->queue);
    $result = $this->qh->getUpdatedRecords();
    $this->assertFalse($result);
  }

}
