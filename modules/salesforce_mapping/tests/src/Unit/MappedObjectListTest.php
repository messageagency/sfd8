<?php

/**
 * @file
 * Contains \Drupal\Tests\Core\Entity\EntityListBuilderTest.
 */

namespace Drupal\Tests\salesforce_mapping\Unit;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\entity_test\EntityTestListBuilder;
use Drupal\Tests\UnitTestCase;
use Drupal\salesforce_mapping\MappedObjectList;
//use Drupal\Core\Entity\EntityStorageInterface;


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
   * The module handler used for testing.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $moduleHandler;

  /**
   * The translation manager used for testing.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $translationManager;

  /**
   * The role storage used for testing.
   *
   * @var \Drupal\user\RoleStorageInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $roleStorage;

  /**
   * The service container used for testing.
   *
   * @var \Drupal\Core\DependencyInjection\ContainerBuilder
   */
  protected $container;

  /**
   * The entity used to construct the EntityListBuilder.
   *
   * @var \Drupal\user\RoleInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $role;

  /**
   * The EntityListBuilder object to test.
   *
   * @var \Drupal\Core\Entity\EntityListBuilder
   */
  protected $entityListBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    //$this->role = $this->getMock('Drupal\user\RoleInterface');
    //$this->roleStorage = $this->getMock('\Drupal\user\RoleStorageInterface');
    //$this->moduleHandler = $this->getMock('\Drupal\Core\Extension\ModuleHandlerInterface');
    $this->entityType = $this->getMock('\Drupal\Core\Entity\EntityTypeInterface');
    $this->translationManager = $this->getMock('\Drupal\Core\StringTranslation\TranslationInterface');
    $this->storage = $this->getMock('\Drupal\Core\Entity\EntityStorageInterface');
    $this->url_generator = $this->getMock('\Drupal\Core\Routing\UrlGeneratorInterface');
    $this->entity = $this->getMock('DDrupal\salesforce_mapping\Entity\MappedObjectInterface');
    //$this->entityListBuilder = new TestEntityListBuilder($this->entityType, $this->roleStorage, $this->moduleHandler);
    $this->container = new ContainerBuilder();
    \Drupal::setContainer($this->container);
  }

  /**
   * @covers ::getOperations
   */
/*
  public function testGetOperations() {
    $operation_name = $this->randomMachineName();
    $operations = array(
      $operation_name => array(
        'title' => $this->randomMachineName(),
      ),
    );
    $this->moduleHandler->expects($this->once())
      ->method('invokeAll')
      ->with('entity_operation', array($this->role))
      ->will($this->returnValue($operations));
    $this->moduleHandler->expects($this->once())
      ->method('alter')
      ->with('entity_operation');

    $this->container->set('module_handler', $this->moduleHandler);

    $this->role->expects($this->any())
      ->method('access')
      ->will($this->returnValue(AccessResult::allowed()));
    $this->role->expects($this->any())
      ->method('hasLinkTemplate')
      ->will($this->returnValue(TRUE));
    $url = $this->getMockBuilder('\Drupal\Core\Url')
      ->disableOriginalConstructor()
      ->getMock();
    $url->expects($this->any())
      ->method('toArray')
      ->will($this->returnValue(array()));
    $this->role->expects($this->any())
      ->method('urlInfo')
      ->will($this->returnValue($url));

    $list = new EntityListBuilder($this->entityType, $this->roleStorage, $this->moduleHandler);
    $list->setStringTranslation($this->translationManager);

    $operations = $list->getOperations($this->role);
    $this->assertInternalType('array', $operations);
    $this->assertArrayHasKey('edit', $operations);
    $this->assertInternalType('array', $operations['edit']);
    $this->assertArrayHasKey('title', $operations['edit']);
    $this->assertArrayHasKey('delete', $operations);
    $this->assertInternalType('array', $operations['delete']);
    $this->assertArrayHasKey('title', $operations['delete']);
    $this->assertArrayHasKey($operation_name, $operations);
    $this->assertInternalType('array', $operations[$operation_name]);
    $this->assertArrayHasKey('title', $operations[$operation_name]);
  }
*/
  /**
   * @covers ::render
   */
  public function testRender() {

    $list = new MappedObjectList($this->entityType, $this->storage, $this->url_generator);
    $list->setStringTranslation($this->translationManager);
    $build = $list->render();
    $this->assertArrayHasKey('#markup', $build['description']);
  }

  /**
   * @covers ::buildHeader
   */
  public function testBuildHeader() {
    $list = new MappedObjectList($this->entityType, $this->storage, $this->url_generator);
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
    $entity_id = new \stdClass();
    $entity_id->value = 1;
    $this->entity->entity_id = $entity_id;
    $entity_type = new \stdClass();
    $entity_type->value = 'Foo';
    $this->entity->entity_type = $entity_type;
    $this->entity->expects($this->any())
      ->method('sfid')
      ->will($this->returnValue('1234567890abcdeAAA'));
    //$this->entity->expects($this->any())
    //  ->method('sfid')
    //  ->will($this->returnValue('1234567890abcdeAAA'));
    var_dump($this->entity);
    $list = new MappedObjectList($this->entityType, $this->storage, $this->url_generator);
    $row = $list->buildRow($this->entity);
    $this->assertArrayHasKey('id', $row);
    $this->assertArrayHasKey('entity_id', $row);
    $this->assertArrayHasKey('entity_type', $row);
    $this->assertArrayHasKey('salesforce_id', $row);
    $this->assertArrayHasKey('changed', $row);
  }

}


class TestEntityListBuilder extends EntityTestListBuilder {
  public function buildOperations(EntityInterface $entity) {
    return array();
  }

}
