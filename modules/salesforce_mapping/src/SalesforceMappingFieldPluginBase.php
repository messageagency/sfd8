<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Plugin\SalesforceMappingFieldPluginBase.
 */

namespace Drupal\salesforce_mapping;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce\Rest\RestClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\salesforce_mapping\SalesforceMappingStorage;
use Drupal\salesforce_mapping\MappedObjectStorage;

/**
 * Defines a base Salesforce Mapping Field Plugin implementation.
 * Extenders need to implement SalesforceMappingFieldPluginInterface::value() and
 * PluginFormInterface::buildConfigurationForm().
 * @see Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface
 * @see Drupal\Core\Plugin\PluginFormInterface
 */
abstract class SalesforceMappingFieldPluginBase extends PluginBase implements SalesforceMappingFieldPluginInterface, PluginFormInterface, ConfigurablePluginInterface, ContainerFactoryPluginInterface {

  protected $label;
  protected $id;
  protected $entityTypeBundleInfo;
  protected $entityFieldManager;
  protected $salesforceClient;

  // @see SalesforceMappingFieldPluginInterface::value()
  // public function value();

  // @see PluginFormInterface::buildConfigurationForm().
  // public function buildConfigurationForm(array $form, FormStateInterface $form_state);

  /**
   * Storage handler for SF mappings
   *
   * @var SalesforceMappingStorage
   */
  protected $mapping_storage;

  /**
   * Storage handler for Mapped Objects
   *
   * @var MappedObjectStorage
   */
  protected $mapped_object_storage

  /**
   * Constructs a \Drupal\salesforce_mapping\Plugin\SalesforceMappingFieldPluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $mapping
   *   The entity manager to get the SF listing, mapped entity, etc.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $mapping
   *   The entity manager to get the SF listing, mapped entity, etc.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityFieldManagerInterface $entity_field_manager, RestClient $rest_client, SalesforceMappingStorage $mapping_storage, MappedObjectStorage $mapped_object_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
    $this->salesforceClient = $rest_client;
    $this->mapping_storage = $mapping_storage;
    $this->mapped_object_storage = $mapped_object_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, 
      $container->get('entity_type.bundle.info'),   
      $container->get('entity_field.manager'),
      $container->get('salesforce.client'),
      $container->get('salesforce.salesforce_mapping_storage'),
      $container->get('salesforce.mapped_object_storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isAllowed(SalesforceMappingInterface $mapping) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * In order to set a config value to null, use setConfiguration()
   */
  public function config($key = NULL, $value = NULL) {
    if ($key === NULL) {
      return $this->configuration;
    }
    if ($value !== NULL) {
      $this->configuration[$key] = $value;
    }
    if (array_key_exists($key, $this->configuration)) {
      return $this->configuration[$key];
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'direction' => SALESFORCE_MAPPING_DIRECTION_SYNC,
      'salesforce_field' => [],
      'drupal_field_type' => $this->getPluginId(),
      'drupal_field_value' => '',
      'locked' => FALSE,
      'mapping_id' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $pluginForm = array();
    $plugin_def = $this->getPluginDefinition();

    // Extending plugins will probably inject most of their own logic here:
    $pluginForm['drupal_field_value'] = [
      '#title' => $plugin_def['label'],
    ];

    $pluginForm['salesforce_field'] = [
      '#title' => t('Salesforce field'),
      '#type' => 'select',
      '#description' => t('Select a Salesforce field to map.'),
      // @TODO MULTIPLE SF FIELDS FOR ONE MAPPING FIELD NOT IN USE:
      // '#multiple' => (isset($drupal_field_type['salesforce_multiple_fields']) && $drupal_field_type['salesforce_multiple_fields']) ? TRUE : FALSE,
      '#options' => $this->get_salesforce_field_options($form['#entity']->getSalesforceObjectType()),
      '#default_value' => $this->config('salesforce_field'),
      '#empty_option' => $this->t('- Select -'),
    ];

    $pluginForm['direction'] = [
      '#title' => t('Direction'),
      '#type' => 'radios',
      '#options' => [
        SALESFORCE_MAPPING_DIRECTION_DRUPAL_SF => t('Drupal to SF'),
        SALESFORCE_MAPPING_DIRECTION_SF_DRUPAL => t('SF to Drupal'),
        SALESFORCE_MAPPING_DIRECTION_SYNC => t('Sync'),
      ],
      '#required' => TRUE,
      '#default_value' => $this->config('direction') ? $this->config('direction') : SALESFORCE_MAPPING_DIRECTION_SYNC,
    ];

    return $pluginForm;
  }


  /**
   * Implements PluginFormInterface::validateConfigurationForm().
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    
  }

  /**
   * Implements PluginFormInterface::submitConfigurationForm().
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    
  }

  /**
   * @TODO: this implementation from ConfigurablePluginInterface
   * Calculates dependencies for the configured plugin.
   *
   * Dependencies are saved in the plugin's configuration entity and are used to
   * determine configuration synchronization order. For example, if the plugin
   * integrates with specific user roles, this method should return an array of
   * dependencies listing the specified roles.
   *
   * @return array
   *   An array of dependencies grouped by type (config, content, module,
   *   theme). For example:
   *   @code
   *   array(
   *     'config' => array('user.role.anonymous', 'user.role.authenticated'),
   *     'content' => array('node:article:f0a189e6-55fb-47fb-8005-5bef81c44d6d'),
   *     'module' => array('node', 'user'),
   *     'theme' => array('seven'),
   *   );
   *   @endcode
   *
   * @see \Drupal\Core\Config\Entity\ConfigDependencyManager
   * @see \Drupal\Core\Entity\EntityInterface::getConfigDependencyName()
   */
  public function calculateDependencies() {
    
  }

  /**
   * Implements SalesforceMappingFieldPluginInterface::label().
   */
  public function label() {
    return $this->get('label');
  }

  /**
   * Implements SalesforceMappingFieldPluginInterface::get().
   */
  public function get($key) {
    return $this->config($key);
  }

  /**
   * Implements SalesforceMappingFieldPluginInterface::get().
   */
  public function set($key, $value) {
    $this->$key = $value;
  }

  /**
   * @return bool
   *  Whether or not this field should be pushed to Salesforce.
   * @TODO This needs a better name. Could be mistaken for a verb.
   */
  public function push() {
    return in_array($this->config('direction'), [SALESFORCE_MAPPING_DIRECTION_DRUPAL_SF, SALESFORCE_MAPPING_DIRECTION_SYNC]);
  }

  /**
   * @return bool
   *  Whether or not this field should be pulled from Salesforce to Drupal.
   * @TODO This needs a better name. Could be mistaken for a verb.
   */
  public function pull() {
    return in_array($this->config('direction'), [SALESFORCE_MAPPING_DIRECTION_SYNC, SALESFORCE_MAPPING_DIRECTION_SF_DRUPAL]);
  }

  /**
   * Helper to retreive a list of fields for a given object type.
   *
   * @param string $salesforce_object_type
   *   The object type of whose fields you want to retreive.
   *
   * @return array
   *   An array of values keyed by machine name of the field with the label as
   *   the value, formatted to be appropriate as a value for #options.
   */
  protected function get_salesforce_field_options($sfobject_name) {
    // Static cache since this function is called frequently across many
    // different object instances.
    $options = &drupal_static(__CLASS__.__FUNCTION__, []);
    if (empty($options[$sfobject_name])) {
      $describe = $this->salesforceClient->objectDescribe($sfobject_name);
      $options[$sfobject_name] = $describe->getFieldOptions();
    }
    return $options[$sfobject_name];
  }

}
