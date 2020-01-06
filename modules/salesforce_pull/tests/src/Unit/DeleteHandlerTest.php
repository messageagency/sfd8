<?php

namespace Drupal\Tests\salesforce_pull\Unit;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Entity\EntityStorageBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\MappedObjectStorage;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\salesforce_mapping\SalesforceMappingStorage;
use Drupal\salesforce_pull\DeleteHandler;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\Entity\User;
use Prophecy\Argument;

/**
 * Test Object instantitation.
 *
 * @group salesforce_pull
 */
class DeleteHandlerTest extends UnitTestCase {

  /**
   * Required modules.
   *
   * @var array
   */
  protected static $modules = ['salesforce_pull'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $result = [
      'totalSize' => 1,
      'done' => TRUE,
      'deletedRecords' => [
        [
          'id' => '1234567890abcde',
          'attributes' => ['type' => 'dummy'],
          'name' => 'Example',
        ],
      ],
    ];

    $prophecy = $this->prophesize(RestClientInterface::CLASS);
    $prophecy->getDeleted(Argument::any(), Argument::any(), Argument::any())
      ->willReturn($result);
    $this->sfapi = $prophecy->reveal();

    // Mock an atribtary Drupal entity.
    $this->entity = $this->getMockBuilder(User::CLASS)
      ->disableOriginalConstructor()
      ->getMock();
    $this->entity->expects($this->any())->method('delete')->willReturn(TRUE);
    $this->entity->expects($this->any())->method('id')->willReturn(1);
    $this->entity->expects($this->any())->method('label')->willReturn('foo');

    $this->mapping = $this->getMockBuilder(SalesforceMappingInterface::CLASS)->getMock();
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
      ->willReturn(TRUE);

    // Mock mapped object.
    $this->entityTypeId = new \stdClass();
    $this->entityId = new \stdClass();
    $this->entityRef = new \stdClass();
    $this->entityTypeId->value = 'test';
    $this->entityId->value = '1';
    $this->entityRef->entity = $this->mapping;

    $this->mappedObject = $this->getMockBuilder(MappedObjectInterface::CLASS)->getMock();
    $this->mappedObject
      ->expects($this->any())
      ->method('delete')
      ->willReturn(TRUE);
    $this->mappedObject
      ->expects($this->any())
      ->method('getMapping')
      ->willReturn($this->mapping);
    $this->mappedObject
      ->expects($this->any())
      ->method('getFieldDefinitions')
      ->willReturn(['drupal_entity', 'salesforce_mapping']);
    $this->mappedObject
      ->expects($this->any())
      ->method('getMappedEntity')
      ->willReturn($this->entity);

    // Mock mapping ConfigEntityStorage object.
    $prophecy = $this->prophesize(SalesforceMappingStorage::CLASS);
    $prophecy->loadByProperties(Argument::any())->willReturn([$this->mapping]);
    $prophecy->load(Argument::any())->willReturn($this->mapping);
    $prophecy->loadMultiple()->willReturn([
      $this->mapping,
    ]);
    $this->configStorage = $prophecy->reveal();

    // Mock mapped object EntityStorage object.
    $this->entityStorage = $this->getMockBuilder(MappedObjectStorage::CLASS)
      ->disableOriginalConstructor()
      ->getMock();
    $this->entityStorage->expects($this->any())
      ->method('loadBySfid')
      ->willReturn([$this->mappedObject]);

    // Mock Drupal entity EntityStorage object.
    $prophecy = $this->prophesize(EntityStorageBase::CLASS);
    $prophecy->load(Argument::any())->willReturn($this->entity);
    $this->drupalEntityStorage = $prophecy->reveal();

    // Mock EntityTypeManagerInterface.
    $prophecy = $this->prophesize(EntityTypeManagerInterface::CLASS);
    $prophecy->getStorage('salesforce_mapping')->willReturn($this->configStorage);
    $prophecy->getStorage('salesforce_mapped_object')->willReturn($this->entityStorage);
    $prophecy->getStorage('test')->willReturn($this->drupalEntityStorage);
    $this->etm = $prophecy->reveal();

    // Mock state.
    $prophecy = $this->prophesize(StateInterface::CLASS);
    $prophecy->get('salesforce.mapping_pull_info', Argument::any())->willReturn([1 => ['last_delete_timestamp' => '1485787434']]);
    $prophecy->set('salesforce.mapping_pull_info', Argument::any())->willReturn(NULL);
    $this->state = $prophecy->reveal();

    // Mock event dispatcher.
    $prophecy = $this->prophesize(ContainerAwareEventDispatcher::CLASS);
    $prophecy->dispatch(Argument::any(), Argument::any())->willReturn();
    $this->ed = $prophecy->reveal();

    $this->dh = new DeleteHandler(
      $this->sfapi,
      $this->etm,
      $this->state,
      $this->ed
    );
  }

  /**
   * Test object creation.
   */
  public function testObject() {
    $this->assertTrue($this->dh instanceof DeleteHandler);
  }

  /**
   * Test processDeletedRecords.
   */
  public function testGetUpdatedRecords() {
    $result = $this->dh->processDeletedRecords();
    $this->assertTrue($result);
  }

}
