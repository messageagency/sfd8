<?php
namespace Drupal\Tests\salesforce_pull\Unit;

use Drupal\Core\Entity\Entity;
use Drupal\Core\Entity\EntityStorageBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\State\StateInterface;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\MappedObjectStorage;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\salesforce_mapping\SalesforceMappingStorage;
use Drupal\salesforce_pull\DeleteHandler;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce\SObject;
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

class DeleteHandlerTest extends UnitTestCase {
  static $modules = ['salesforce_pull'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $result = [
      'totalSize' => 1,
      'done' => true,
      'deletedRecords' => [
        [
          'id' => '1234567890abcde',
          'attributes' => ['type' => 'dummy',],
          'name' => 'Example',
        ],
      ]
    ];

    $prophecy = $this->prophesize(RestClient::CLASS);
    $prophecy->getDeleted(Argument::any(),Argument::any(),Argument::any())
      ->willReturn($result); // revisit
    $this->sfapi = $prophecy->reveal();

    // mock Drupal entity
    $prophecy = $this->prophesize(Entity::CLASS);
    $prophecy->delete()->willReturn(true);
    $prophecy->id()->willReturn(1);
    $this->entity = $prophecy->reveal();

    $this->mapping = $this->getMock(SalesforceMappingInterface::CLASS);
      // ->setMethods(['__get', 'getSalesforceObjectType', 'getPullFieldsArray', 'checkTriggers'])
      // ->getMock();
    $this->mapping->expects($this->any())
      ->method('__get')
      ->with($this->equalTo('id'))
      ->willReturn(1);
    $this->mapping->expects($this->any())
      ->method('__get')
      ->with($this->equalTo('entity'))
      ->willReturn($this->entity);
    $this->mapping->expects($this->any())
      ->method('getSalesforceObjectType')
      ->willReturn('default');
    $this->mapping->expects($this->any())
      ->method('getPullFieldsArray')
      ->willReturn(['Name' => 'Name', 'Account Number' => 'Account Number']);
    $this->mapping->expects($this->any())
      ->method('checkTriggers')
      ->with([MappingConstants::SALESFORCE_MAPPING_SYNC_SF_DELETE])
      ->willReturn(true);

    // mock mapped object
    $this->entityTypeId = new \stdClass();
    $this->entityId = new \stdClass();
    $this->entityRef = new \stdClass();
    $this->entityTypeId->value = 'test';
    $this->entityId->value = '1';
    $this->entityRef->entity = $this->mapping;

    $this->mappedObject = $this->getMock(MappedObjectInterface::CLASS);
    $this->mappedObject
      ->expects($this->any())
      ->method('delete')
      ->willReturn(true);
    $this->mappedObject
      ->expects($this->any())
      ->method('getMapping')
      ->willReturn($this->mapping);
    $this->mappedObject
      ->expects($this->any())
      ->method('getFieldDefinitions')
      ->willReturn(['entity_type_id','entity_id','salesforce_mapping']);
    $this->mappedObject
      ->expects($this->any())
      ->method('getMappedEntity')
      ->willReturn($this->entity);

    // mock mapping ConfigEntityStorage object
    $prophecy = $this->prophesize(SalesforceMappingStorage::CLASS);
    $prophecy->loadByProperties(Argument::any())->willReturn([$this->mapping]);
    $prophecy->load(Argument::any())->willReturn($this->mapping);
    $prophecy->getMappedSobjectTypes()->willReturn([
      'default'
    ]);
    $this->configStorage = $prophecy->reveal();

    // mock mapped object EntityStorage object
    $this->entityStorage = $this->getMockBuilder(MappedObjectStorage::CLASS)
      ->disableOriginalConstructor()
      ->getMock();
    $this->entityStorage->expects($this->any())
      ->method('loadBySfid')
      ->willReturn([$this->mappedObject]);

    // mock Drupal entity EntityStorage object
    $prophecy = $this->prophesize(EntityStorageBase::CLASS);
    $prophecy->load(Argument::any())->willReturn($this->entity);
    $this->drupalEntityStorage = $prophecy->reveal();

    // mock EntityTypeManagerInterface
    $prophecy = $this->prophesize(EntityTypeManagerInterface::CLASS);
    $prophecy->getStorage('salesforce_mapping')->willReturn($this->configStorage);
    $prophecy->getStorage('salesforce_mapped_object')->willReturn($this->entityStorage);
    $prophecy->getStorage('test')->willReturn($this->drupalEntityStorage);
    $this->etm = $prophecy->reveal();

    // mock state
    $prophecy = $this->prophesize(StateInterface::CLASS);
    $prophecy->get('salesforce_pull_last_delete_default', Argument::any())->willReturn('1485787434');
    $prophecy->set('salesforce_pull_last_delete_default', Argument::any())->willReturn(null);
    $this->state = $prophecy->reveal();

    // mock logger
    $prophecy = $this->prophesize(LoggerInterface::CLASS);
    $prophecy->log(Argument::any(), Argument::any(), Argument::any())->willReturn(null);
    $this->logger = $prophecy->reveal();

    // mock server
    $prophecy = $this->prophesize(ServerBag::CLASS);
    $prophecy->get(Argument::any())->willReturn('1485787434');
    $this->server = $prophecy->reveal();

    // mock request
    $prophecy = $this->prophesize(Request::CLASS);
    $prophecy->server = $this->server;
    $this->request = $prophecy->reveal();

    $this->dh = DeleteHandler::create(
      $this->sfapi,
      $this->etm,
      $this->state,
      $this->logger,
      $this->request
    );
  }

  /**
   * Test object instantiation
   */
  public function testObject() {
    $this->assertTrue($this->dh instanceof DeleteHandler);
  }

  /**
   * Test handler operation, good data
   */
  public function testGetUpdatedRecords() {
    $result = $this->dh->processDeletedRecords();
    $this->assertTrue($result);
  }
}
