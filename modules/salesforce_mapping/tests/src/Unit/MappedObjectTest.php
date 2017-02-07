<?php

namespace Drupal\Tests\salesforce_mapping\Unit;

use Drupal\salesforce_mapping\SalesforceMappingFieldPluginManager;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\salesforce_mapping\Plugin\SalesforceMappingField\Properties;
use Prophecy\Argument;
use Drupal\Tests\UnitTestCase;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\SFID;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_mapping\Entity\MappedObject;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface;

/**
 * Test Mapped Object instantitation
 * @coversDefaultClass \Drupal\salesforce_mapping\Entity\MappedObject
 * @group salesforce_mapping
 */

class MappedObjectTest extends UnitTestCase {
  static $modules = ['salesforce_mapping'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->entityTypeId = $this->randomMachineName();
    $this->bundle = $this->randomMachineName();
    $this->mapped_object_id = 1;
    $this->salesforce_id = '1234567890abcdeAAA';
    $this->mapping_id = 1;
    $this->entity_id = 1;

    $values = array(
      'id' => $this->mapped_object_id,
      // 'defaultLangcode' => array(LanguageInterface::LANGCODE_DEFAULT => 'en'),
      'entity_id' => $this->entity_id,
      'entity_type_id' => $this->entityTypeId,
      'salesforce_id' => $this->salesforce_id,
      'salesforce_mapping' => $this->mapping_id,
    );

    $this->sfid = $this->getMockBuilder(SFID::CLASS)
      ->setConstructorArgs([$this->salesforce_id])
      ->getMock();
    $this->sfid->expects($this->any())
      ->method('__toString')
      ->willReturn($this->salesforce_id);

    $this->entityType = $this->getMock('\Drupal\Core\Entity\EntityTypeInterface');
    $this->entityType->expects($this->any())
      ->method('getKeys')
      ->will($this->returnValue([
        'id' => 'id',
        'uuid' => 'uuid',
    ]));

    $this->entityManager = $this->getMock('\Drupal\Core\Entity\EntityManagerInterface');
    $this->entityManager->expects($this->any())
      ->method('getDefinition')
      ->with($this->entityTypeId)
      ->will($this->returnValue($this->entityType));

    $this->mappedObjectEntityType = $this->getMock('\Drupal\Core\Entity\EntityTypeInterface');
    $this->mappedObjectEntityType->expects($this->any())
      ->method('getKeys')
      ->will($this->returnValue([
        'id' => 'id',
        'entity_id' => 'entity_id',
        'salesforce_id' => 'salesforce_id'
    ]));

    $this->entityManager = $this->getMock('\Drupal\Core\Entity\EntityManagerInterface');
    $this->entityManager->expects($this->any())
      ->method('getDefinition')
      ->with('salesforce_mapped_object')
      ->will($this->returnValue($this->mappedObjectEntityType));

    $this->logger_factory = $this->getMock('\Drupal\Core\Logger\LoggerChannelFactoryInterface');

    $this->event_dispatcher = $this->getMock('\Symfony\Component\EventDispatcher\EventDispatcherInterface');

    $this->client = $this->getMock(RestClientInterface::CLASS);

    $this->fieldTypePluginManager = $this->getMockBuilder('\Drupal\Core\Field\FieldTypePluginManager')
      ->disableOriginalConstructor()
      ->getMock();
    $this->fieldTypePluginManager->expects($this->any())
      ->method('getDefaultStorageSettings')
      ->will($this->returnValue(array()));
    $this->fieldTypePluginManager->expects($this->any())
      ->method('getDefaultFieldSettings')
      ->will($this->returnValue(array()));
    $this->fieldTypePluginManager->expects($this->any())
      ->method('createFieldItemList')
      ->will($this->returnValue(
        $this->getMock('Drupal\Core\Field\FieldItemListInterface')));

    $container = new ContainerBuilder();
    $container->set('entity.manager', $this->entityManager);
    $container->set('salesforce.client', $this->client);
    $container->set('event_dispatcher', $this->event_dispatcher);
    $container->set('logger.factory', $this->logger_factory);
    $container->set('plugin.manager.field.field_type', $this->fieldTypePluginManager);
    \Drupal::setContainer($container);

    $this->entity = $this->getMock('\Drupal\Core\Entity\ContentEntityInterface');
    $this->entity
      ->expects($this->any())
      ->method('id')
      ->willReturn($this->entity_id);
  
    $this->entity
      ->expects($this->any())
      ->method('isTranslatable')
      ->willReturn(FALSE);

    $this->fieldDefinitions = array(
      'id' => BaseFieldDefinition::create('integer'),
      'entity_id' => BaseFieldDefinition::create('integer'),
      'entity_type_id' => BaseFieldDefinition::create('string'),
      'salesforce_id' => BaseFieldDefinition::create('string'),
      'salesforce_mapping' => BaseFieldDefinition::create('entity_reference'),
    );

    $this->entityManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->with('salesforce_mapped_object', 'salesforce_mapped_object')
      ->will($this->returnValue($this->fieldDefinitions));

    // mock salesforce mapping
    $this->mapping = $this->getMock(SalesforceMappingInterface::CLASS);
    $this->mapping
      ->expects($this->any())
      ->method('getFieldMappings')
      ->willReturn([]);
    $this->mapping
      ->expects($this->any())
      ->method('getSalesforceObjectType')
      ->willReturn('dummy_sf_object_type');

    $this->mapped_object = $this->getMockBuilder(MappedObject::CLASS)
      ->disableOriginalConstructor()
      ->setMethods(['getMappedEntity', 'getMapping', 'getEntityType', 'sfid', 'set', 'save', 'setNewRevision', 'client'])
      ->getMock();
    $this->mapped_object->expects($this->any())
      ->method('getMappedEntity')
      ->willReturn($this->entity);
    $this->mapped_object->expects($this->any())
      ->method('getMapping')
      ->willReturn($this->mapping);
    $this->mapped_object->expects($this->any())
      ->method('getEntityType')
      ->willReturn($this->mappedObjectEntityType);
    $this->mapped_object->expects($this->any())
      ->method('set')
      ->willReturn($this->mapped_object);
    $this->mapped_object->expects($this->any())
      ->method('client')
      ->willReturn($this->client);
  }

  /**
   * @covers ::push
   */
  public function testPushUpsert() {
    // First pass: test upsert
    $this->mapping->expects($this->any())
      ->method('hasKey')
      ->will($this->returnValue(TRUE));
    $this->client->expects($this->once())
      ->method('objectUpsert')
      ->willReturn(NULL);
    $this->assertNull($this->mapped_object->push());
  }

  /**
   * @covers ::push
   */
  public function testPushUpdate() {
    // Second pass: test update
    $this->mapping->expects($this->once())
      ->method('hasKey')
      ->willReturn(FALSE);
    $this->mapped_object->expects($this->any())
      ->method('sfid')
      ->willReturn($this->sfid);
    $this->client->expects($this->once())
      ->method('objectUpdate')
      ->willReturn(NULL);
    $this->assertNull($this->mapped_object->push());
  }
  
  /**
   * @covers ::push
   */
  public function testPushCreate() {
    // Third pass: test create
    $this->mapping->expects($this->once())
      ->method('hasKey')
      ->will($this->returnValue(FALSE));
    $this->mapped_object->expects($this->any())
      ->method('sfid')
      ->willReturn(FALSE);
    $this->client->expects($this->once())
      ->method('objectCreate')
      ->willReturn($this->sfid);

    $result = $this->mapped_object->push();
    $this->assertTrue($result instanceof SFID);
    $this->assertEquals($this->salesforce_id, (string)$result);
  }

  /**
   * @covers ::pushDelete
   */
  public function testPushDelete() {
    $this->client->expects($this->once())
      ->method('objectDelete')
      ->willReturn(NULL);
    $this->assertEquals($this->mapped_object, $this->mapped_object->pushDelete());
  }

  /**
   * @covers ::pull
   */
  public function testPull() {
    // @TODO writeme
  }

}