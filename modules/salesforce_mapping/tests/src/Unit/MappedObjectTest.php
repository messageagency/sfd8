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
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_mapping\Entity\MappedObject;

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
        'revision' => 'revision',
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
      ->will($this->returnValue($this->getMock('Drupal\Core\Field\FieldItemListInterface')));

    $container = new ContainerBuilder();
    $container->set('entity.manager', $this->entityManager);
    $container->set('salesforce.client', $this->client);
    $container->set('event_dispatcher', $this->event_dispatcher);
    $container->set('logger.factory', $this->logger_factory);
    $container->set('plugin.manager.field.field_type', $this->fieldTypePluginManager);
    \Drupal::setContainer($container);

    // mock salesforce mapping
    $this->mapping_id = 1;
    $prophecy = $this->prophesize(SalesforceMappingInterface::CLASS);
    $this->mapping = $prophecy->reveal();

    $this->entity_id = 1;
    $this->entity = $this->getMock('\Drupal\Core\Entity\ContentEntityInterface');
    $this->entity
      ->expects($this->any())
      ->method('id')
      ->willReturn($this->entity_id);
  
    $this->entity
      ->expects($this->any())
      ->method('isTranslatable')
      ->willReturn(FALSE);

    $this->mapped_object_id = 1;
    $this->salesforce_id = '1234567890abcdeAAA';
    $values = array(
      'id' => $this->mapped_object_id,
      // 'defaultLangcode' => array(LanguageInterface::LANGCODE_DEFAULT => 'en'),
      'revision_id' => 1,
      'entity_id' => $this->entity_id,
      'entity_type_id' => $this->entityTypeId,
      'salesforce_id' => $this->salesforce_id,
      'salesforce_mapping' => $this->mapping_id,
    );

    $this->fieldDefinitions = array(
      'id' => BaseFieldDefinition::create('integer'),
      'revision_id' => BaseFieldDefinition::create('integer'),
      'entity_id' => BaseFieldDefinition::create('integer'),
      'entity_type_id' => BaseFieldDefinition::create('string'),
      'salesforce_id' => BaseFieldDefinition::create('string'),
      'salesforce_mapping' => BaseFieldDefinition::create('entity_reference'),
    );

    $this->entityManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->with('salesforce_mapped_object', 'salesforce_mapped_object')
      ->will($this->returnValue($this->fieldDefinitions));

    $this->mapped_object = $this->getMockForAbstractClass('\Drupal\salesforce_mapping\Entity\MappedObjectInterface', array($values));
    $this->mapped_object->expects($this->any())
      ->method('getMappedEntity')
      ->willReturn($this->entity);
    
  }

  /**
   * @covers ::push
   */
  public function testPush() {

  }

  /**
   * @covers ::pushDelete
   */
  public function testPushDelete() {
    
  }

  /**
   * @covers ::pull
   */
  public function testPull() {
    
  }

}