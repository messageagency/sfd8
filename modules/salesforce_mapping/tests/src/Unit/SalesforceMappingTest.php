<?php

namespace Drupal\Tests\salesforce_mapping\Unit;

use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\salesforce\SelectQuery;
use Drupal\Tests\UnitTestCase;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\salesforce_mapping\Plugin\SalesforceMappingField\Properties;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginManager;
use Prophecy\Argument;

/**
 * Test Object instantitation.
 *
 * @group salesforce_mapping
 */
class SalesforceMappingTest extends UnitTestCase {

  /**
   * Required modules.
   *
   * @var array
   */
  static public $modules = ['salesforce_mapping'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->id = $this->randomMachineName();
    $this->saleforceObjectType = $this->randomMachineName();
    $this->drupalEntityTypeId = $this->randomMachineName();
    $this->drupalBundleId = $this->randomMachineName();
    $this->values = [
      'id' => $this->id,
      'langcode' => 'en',
      'uuid' => '3bb9ee60-bea5-4622-b89b-a63319d10b3a',
      'label' => 'Test Mapping',
      'weight' => 0,
      'type' => 'salesforce_mapping',
      'key' => 'Drupal_id__c',
      'async' => 1,
      'pull_trigger_date' => 'LastModifiedDate',
      'push_limit' => 0,
      'push_frequency' => 0,
      'pull_frequency' => 0,
      'sync_triggers' => [
        MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_CREATE => 1,
        MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_UPDATE => 1,
        MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE => 1,
        MappingConstants::SALESFORCE_MAPPING_SYNC_SF_CREATE => 1,
        MappingConstants::SALESFORCE_MAPPING_SYNC_SF_UPDATE => 1,
        MappingConstants::SALESFORCE_MAPPING_SYNC_SF_DELETE => 1,
      ],
      'salesforce_object_type' => $this->saleforceObjectType,
      'drupal_entity_type' => $this->drupalEntityTypeId,
      'drupal_bundle' => $this->drupalBundleId,
      'field_mappings' => [
        [
          'drupal_field_type' => 'properties',
          'drupal_field_value' => 'title',
          'salesforce_field' => 'Name',
          'direction' => 'sync',
        ],
        [
          'drupal_field_type' => 'properties',
          'drupal_field_value' => 'nid',
          'salesforce_field' => 'Drupal_id_c',
          'direction' => 'sync',
        ],
      ],
    ];

    // Mock EntityType Definition.
    $this->entityTypeId = $this->randomMachineName();
    $this->provider = $this->randomMachineName();
    $prophecy = $this->prophesize(ConfigEntityTypeInterface::CLASS);
    $prophecy->getProvider(Argument::any())->willReturn($this->provider);
    $prophecy->getConfigPrefix(Argument::any())
      ->willReturn('test_provider.' . $this->entityTypeId);
    $this->entityDefinition = $prophecy->reveal();

    // Mock EntityTypeManagerInterface.
    $prophecy = $this->prophesize(EntityTypeManagerInterface::CLASS);
    $prophecy->getDefinition($this->entityTypeId)->willReturn($this->entityDefinition);
    $this->etm = $prophecy->reveal();

    // Mock Properties SalesforceMappingField.
    $prophecy = $this->prophesize(Properties::CLASS);
    $prophecy->pull()->willReturn(TRUE);
    $sf_mapping_field = $prophecy->reveal();

    // Mode field plugin manager.
    $prophecy = $this->prophesize(SalesforceMappingFieldPluginManager::CLASS);
    $prophecy->createInstance(Argument::any(), Argument::any())->willReturn($sf_mapping_field);
    $field_manager = $prophecy->reveal();

    // Mock state.
    $prophecy = $this->prophesize(StateInterface::CLASS);
    $prophecy->get('salesforce.mapping_pull_info', Argument::any())->willReturn([]);
    $prophecy->get('salesforce.mapping_push_info', Argument::any())->willReturn([
      $this->id => [
        'last_timestamp' => 0,
      ],
    ]);
    $prophecy->set('salesforce.mapping_push_info', Argument::any())->willReturn(NULL);
    $this->state = $prophecy->reveal();

    $container = new ContainerBuilder();
    $container->set('state', $this->state);
    \Drupal::setContainer($container);

    $this->mapping = $this->getMockBuilder(SalesforceMapping::CLASS)
      ->setMethods(['fieldManager'])
      ->setConstructorArgs([$this->values, $this->entityTypeId])
      ->getMock();
    $this->mapping->expects($this->any())
      ->method('fieldManager')
      ->willReturn($field_manager);
  }

  /**
   * Test object instantiation.
   */
  public function testObject() {
    $this->assertTrue($this->mapping instanceof SalesforceMapping);
    $this->assertEquals($this->id, $this->mapping->id());
  }

  /**
   * Test getPullFields()
   */
  public function testGetPullFields() {
    $fields_array = $this->mapping->getPullFields();
    $this->assertTrue(is_array($fields_array));
    $this->assertTrue($fields_array[0] instanceof Properties);
  }

  /**
   * Test checkTriggers()
   */
  public function testCheckTriggers() {
    $triggers = $this->mapping->checkTriggers([
      MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_CREATE,
      MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_UPDATE,
    ]);
    $this->assertTrue($triggers);
  }

  /**
   * Test getPullQuery()
   */
  public function testGetPullQuery() {
    $start = strtotime('-5 minutes');
    $stop = time();
    $query = $this->mapping->getPullQuery([], $start, $stop);
    $expectedQuery = new SelectQuery($this->saleforceObjectType);
    $expectedQuery->addCondition($this->mapping->getPullTriggerDate(), gmdate('Y-m-d\TH:i:s\Z', $start), '>');
    $expectedQuery->addCondition($this->mapping->getPullTriggerDate(), gmdate('Y-m-d\TH:i:s\Z', $stop), '<');
    $expectedQuery->fields = $this->mapping->getPullFieldsArray();
    $expectedQuery->fields[] = 'Id';
    $expectedQuery->fields[] = $this->mapping->getPullTriggerDate();
    $expectedQuery->order[$this->mapping->getPullTriggerDate()] = 'ASC';
    $this->assertArrayEquals($expectedQuery->fields, $query->fields);
    $this->assertArrayEquals($expectedQuery->order, $query->order);
    $this->assertArrayEquals($expectedQuery->conditions, $query->conditions);
    $this->assertEquals($expectedQuery->objectType, $query->objectType);
    $this->assertEquals($expectedQuery->limit, $query->limit);
  }

}
