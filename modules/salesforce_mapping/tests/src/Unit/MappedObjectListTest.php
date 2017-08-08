<?php

/**
 * @file
 * Contains \Drupal\Tests\salesforce_mapping\Unit\MappedObjectListTest.
 */

namespace Drupal\Tests\salesforce_mapping\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\StringItem;
use Drupal\Tests\UnitTestCase;
use Drupal\salesforce_mapping\Tests\TestMappedObjectList;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\salesforce_mapping\MappedObjectList
 * @group salesforce_mapping
 */
class MappedObjectListTest extends UnitTestCase {

  /**
   * The entity type used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityType;

  /**
   * The translation manager used for testing.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $translationManager;

  /**
   * The entity storage used for testing.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $storage;

  /**
   * The service container used for testing.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The entity used for testing.
   *
   * @var \Drupal\Core\Entity\ContentEntityBase|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entity;

  /**
   * The query interface used for testing.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $entityQuery;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // mock content entity
    $this->entity = $this->getMockBuilder(ContentEntityBase::CLASS)
      ->setMethods(['__construct', '__get', 'get', 'sfid', 'id', '__isset', 'getAccessControlHandler'])
      ->disableOriginalConstructor()
      ->getMock();
    $this->entity->expects($this->any())
      ->method('__get')
      ->will($this->returnValueMap([
        ['entity_id',$this->setPropertyValue('1')],
        ['entity_type_id', $this->setPropertyValue('Foo')],
        ['changed', $this->setPropertyValue('12:00:00')]
      ]));
    $this->entity->expects($this->any())
      ->method('sfid')
      ->willReturn('1234567890abcdeAAA');

    $this->entityQuery = $this->prophesize(QueryInterface::class);
    $this->entityQuery->sort(Argument::any())->willReturn($this->entityQuery);
    $this->entityQuery->pager(Argument::any())->willReturn($this->entityQuery);
    $this->entityQuery->execute()->willReturn(['foo', 'bar']);

    $this->entityType = $this->prophesize(EntityTypeInterface::class);
    $this->translationManager = $this->getMock('\Drupal\Core\StringTranslation\TranslationInterface');
    $this->storage = $this->prophesize(EntityStorageInterface::class);
    $this->storage->getQuery()->willReturn($this->entityQuery->reveal());
    $this->storage->loadMultiple(['foo', 'bar'])->willReturn([$this->entity]);

    $this->url_generator = $this->getMock('\Drupal\Core\Routing\UrlGeneratorInterface');
    $this->container = new ContainerBuilder();
    \Drupal::setContainer($this->container);
  }

  /**
   * @covers ::render
   */
  public function testRender() {
    $list = new TestMappedObjectList($this->entityType->reveal(), $this->storage->reveal(), $this->url_generator);
    $list->setStringTranslation($this->translationManager);
    $build = $list->render();
    $this->assertArrayHasKey('#markup', $build['description']);
    $this->assertArrayHasKey('table', $build);
  }

  /**
   * @covers ::buildHeader
   */
  public function testBuildHeader() {
    $list = new TestMappedObjectList($this->entityType->reveal(), $this->storage->reveal(), $this->url_generator);
    $list->setStringTranslation($this->translationManager);
    $header = $list->buildHeader();
    $this->assertArrayHasKey('id', $header);
    $this->assertArrayHasKey('entity_id', $header);
    $this->assertArrayHasKey('entity_type', $header);
    $this->assertArrayHasKey('salesforce_id', $header);
    $this->assertArrayHasKey('changed', $header);

  }

  /**
   * @covers ::buildRow
   */
  public function testBuildRow() {
    $list = new TestMappedObjectList($this->entityType->reveal(), $this->storage->reveal(), $this->url_generator);
    $row = $list->buildRow($this->entity);
    $this->assertArrayHasKey('id', $row);
    $this->assertArrayHasKey('entity_id', $row);
    $this->assertArrayHasKey('entity_type', $row);
    $this->assertArrayHasKey('salesforce_id', $row);
    $this->assertArrayHasKey('changed', $row);
  }

  /**
   * Creates a value object for $entity->property_name->value pattern calls.
   *
   * @param $value
   *   Value to be return by $property_name->value.
   *
   * @return \Drupal\Core\Field\Plugin\Field\FieldType\StringItem
   */
  public function setPropertyValue($value) {
    $valueObject = $this->getMockBuilder(StringItem::CLASS)
      ->setMethods(['__get'])
      ->disableOriginalConstructor()
      ->getMock();
    $valueObject->expects($this->any())
      ->method('__get')
      ->with($this->equalTo('value'))
      ->willReturn($value);
    return $valueObject;
  }

}

