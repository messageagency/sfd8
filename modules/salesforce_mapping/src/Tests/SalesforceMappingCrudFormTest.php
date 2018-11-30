<?php

namespace Drupal\salesforce_mapping\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Tests for salesforce admin settings.
 *
 * @group salesforce_mapping
 */
class SalesforceMappingCrudFormTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'salesforce',
    'salesforce_test_rest_client',
    'salesforce_mapping',
    'user',
    'link',
    'dynamic_entity_reference',
  ];

  protected $normalUser;
  protected $adminSalesforceUser;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    // Admin salesforce user.
    $this->adminSalesforceUser = $this->drupalCreateUser(['administer salesforce mapping']);
  }

  /**
   * Tests webform admin settings.
   */
  public function testMappingCrudForm() {
    global $base_path;
    $mappingStorage = \Drupal::entityTypeManager()->getStorage('salesforce_mapping');
    $this->drupalLogin($this->adminSalesforceUser);

    /* Salesforce Mapping Add Form */
    $mapping_name = 'mapping' . rand(100, 10000);
    $post = [
      'id' => $mapping_name,
      'label' => $mapping_name,
      'drupal_entity_type' => 'user',
      'drupal_bundle' => 'user',
      'salesforce_object_type' => 'Contact',
    ];
    $this->drupalPostForm('admin/structure/salesforce/mappings/add', $post, t('Save'));
    $newurl = parse_url($this->getUrl());

    // Make sure the redirect was correct (and therefore form was submitted
    // successfully).
    $this->assertEqual($newurl['path'], $base_path . 'admin/structure/salesforce/mappings/manage/' . $mapping_name . '/fields');
    $mapping = $mappingStorage->load($mapping_name);
    // Make sure mapping was saved correctly.
    $this->assertEqual($mapping->id(), $mapping_name);
    $this->assertEqual($mapping->label(), $mapping_name);

    /* Salesforce Mapping Edit Form */
    // Need to rebuild caches before proceeding to edit link.
    drupal_flush_all_caches();
    $post = [
      'label' => $this->randomMachineName(),
      'drupal_entity_type' => 'user',
      'drupal_bundle' => 'user',
      'salesforce_object_type' => 'Contact',
    ];
    $this->drupalPostForm('admin/structure/salesforce/mappings/manage/' . $mapping_name, $post, t('Save'));
    $this->assertFieldByName('label', $post['label']);

    // Test simply adding a field plugin of every possible type. This is not
    // great coverage, but will at least make sure our plugin definitions don't
    // cause fatal errors.
    $mappingFieldsPluginManager = \Drupal::service('plugin.manager.salesforce_mapping_field');
    $field_plugins = $mappingFieldsPluginManager->getDefinitions();
    foreach ($field_plugins as $definition) {
      if (call_user_func([$definition['class'], 'isAllowed'], $mapping)) {
        $post = [
          'field_type' => $definition['id'],
        ];
        $this->drupalPostForm('admin/structure/salesforce/mappings/manage/' . $mapping_name . '/fields', $post, t('Add a field mapping to get started'));
      }
    }

  }

}
