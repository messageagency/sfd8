<?php

namespace Drupal\Tests\salesforce_mapping\Unit;

use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityType;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce_mapping\SalesforceMappingStorage;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\salesforce_mapping\SalesforceMappingStorage
 * @group salesforce_mapping
 */
class SalesforceMappingStorageTest extends UnitTestCase {

  /**
   * The type ID of the entity under test.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $uuidService;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $languageManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\Prophecy\Prophecy\ProphecyInterface
   */
  protected $configFactory;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $etm;

  /**
   * Entity type under test.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface
   */
  protected $entityType;

  /**
   * Mapping storage service mock.
   *
   * @var \PHPUnit_Framework_MockObject_MockObject
   */
  protected $salesforceMappingStorage;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  protected function setUp() {
    parent::setUp();

    $this->entityTypeId = 'test_entity_type';
    $this->entityType = new ConfigEntityType([
      'id' => $this->entityTypeId,
      'class' => SalesforceMapping::class,
      'provider' => 'the_provider',
      'config_prefix' => 'the_config_prefix',
      'entity_keys' => [
        'id' => 'id',
        'uuid' => 'uuid',
        'langcode' => 'langcode',
      ],
      'list_cache_tags' => [$this->entityTypeId . '_list'],
    ]);

    $this->uuidService = $this->createMock(UuidInterface::class);
    $this->languageManager = $this->createMock(LanguageManagerInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->etm = $this->getMockBuilder(EntityTypeManagerInterface::class)
      ->disableOriginalConstructor()
      ->getMock();
    $this->etm->expects($this->any())
      ->method('getDefinition')
      ->with($this->equalTo('test_entityType'))
      ->willReturn($this->entityType);

    $this->salesforceMappingStorage = $this->getMockBuilder(SalesforceMappingStorage::class)
      ->setConstructorArgs([
        $this->entityTypeId,
        $this->configFactory,
        $this->uuidService,
        $this->languageManager,
        $this->etm,
      ])
      ->disableOriginalConstructor()
      ->setMethods(['loadByProperties'])
      ->getMock();
  }

  /**
   * @covers ::loadByDrupal
   */
  public function testLoadByDrupal() {
    $config_object = $this->prophesize(SalesforceMapping::class);

    $this->salesforceMappingStorage
      ->expects($this->at(0))
      ->method('loadByProperties')
      ->with($this->equalTo(['drupal_entity_type' => $this->entityTypeId]))
      ->willReturn([$config_object->reveal()]);

    $this->salesforceMappingStorage
      ->expects($this->at(1))
      ->method('loadByProperties')
      ->with($this->equalTo(['drupal_entity_type' => 'dummy']))
      ->willReturn([]);

    // Good entity type id provided means config object should be returned.
    $entities = $this->salesforceMappingStorage->loadByDrupal($this->entityTypeId);
    $this->assertEquals([$config_object->reveal()], $entities);

    // Bad entity type provided means config should not be returned.
    $entities = $this->salesforceMappingStorage->loadByDrupal('dummy');
    $this->assertEquals([], $entities);

  }

  /**
   * @covers ::loadPushMappings
   */
  public function testLoadPushMappings() {
    $foo_config_object = $this->prophesize(SalesforceMapping::class);
    $foo_config_object->id()->willReturn('foo');
    $foo_config_object->doesPush()->willReturn(TRUE);

    $bar_config_object = $this->prophesize(SalesforceMapping::class);
    $bar_config_object->id()->willReturn('bar');
    $bar_config_object->doesPush()->willReturn(TRUE);

    $zee_config_object = $this->prophesize(SalesforceMapping::class);
    $zee_config_object->id()->willReturn('zee');
    // Zee does NOT push; make sure it's excluded from our load helper.
    $zee_config_object->doesPush()->willReturn(FALSE);

    $this->salesforceMappingStorage
      ->expects($this->once())
      ->method('loadByProperties')
      ->willReturn([
        'foo' => $foo_config_object->reveal(),
        'bar' => $bar_config_object->reveal(),
        'zee' => $zee_config_object->reveal(),
      ]);

    $entities = $this->salesforceMappingStorage->loadPushMappings($this->entityTypeId);
    $expected = [
      'foo' => $foo_config_object->reveal(),
      'bar' => $bar_config_object->reveal(),
    ];
    $this->assertContainsOnlyInstancesOf(EntityInterface::class, $entities);
    $this->assertEquals($expected, $entities);
  }

  /**
   * @covers ::loadPullMappings
   */
  public function testLoadPullMappings() {
    $foo_config_object = $this->prophesize(SalesforceMapping::class);
    $foo_config_object->id()->willReturn('foo');
    $foo_config_object->doesPull()->willReturn(TRUE);

    $bar_config_object = $this->prophesize(SalesforceMapping::class);
    $bar_config_object->id()->willReturn('bar');
    $bar_config_object->doesPull()->willReturn(TRUE);

    $zee_config_object = $this->prophesize(SalesforceMapping::class);
    $zee_config_object->id()->willReturn('zee');
    // Zee does NOT push; make sure it's excluded from our load helper.
    $zee_config_object->doesPull()->willReturn(FALSE);

    $this->salesforceMappingStorage
      ->expects($this->once())
      ->method('loadByProperties')
      ->willReturn([
        'foo' => $foo_config_object->reveal(),
        'bar' => $bar_config_object->reveal(),
        'zee' => $zee_config_object->reveal(),
      ]);

    $entities = $this->salesforceMappingStorage->loadPullMappings($this->entityTypeId);
    $expected = [
      'foo' => $foo_config_object->reveal(),
      'bar' => $bar_config_object->reveal(),
    ];
    $this->assertContainsOnlyInstancesOf(EntityInterface::class, $entities);
    $this->assertEquals($expected, $entities);
  }

  /**
   * @covers ::getMappedSobjectTypes
   */
  public function testGetMappedSobjectTypes() {
    $foo_config_object = $this->prophesize(SalesforceMapping::class);
    $foo_config_object->id()->willReturn('foo');
    $foo_config_object->getSalesforceObjectType()->willReturn('Account');

    $bar_config_object = $this->prophesize(SalesforceMapping::class);
    $bar_config_object->id()->willReturn('bar');
    $bar_config_object->getSalesforceObjectType()->willReturn('Account');

    $zee_config_object = $this->prophesize(SalesforceMapping::class);
    $zee_config_object->id()->willReturn('zee');
    $zee_config_object->getSalesforceObjectType()->willReturn('Contact');

    $this->salesforceMappingStorage
      ->expects($this->once())
      ->method('loadByProperties')
      ->willReturn([
        $foo_config_object->reveal(),
        $bar_config_object->reveal(),
        $zee_config_object->reveal(),
      ]);

    $object_types = $this->salesforceMappingStorage->getMappedSobjectTypes();
    $expected = ['Account' => 'Account', 'Contact' => 'Contact'];
    $this->assertEquals($expected, $object_types);
  }

}
