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
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  protected $mapping;
  protected $entityTypeBundleInfo;
  protected $entityFieldManager;

  // @see SalesforceMappingFieldPluginInterface::value()
  // public function value();

  // @see PluginFormInterface::buildConfigurationForm().
  // public function buildConfigurationForm(array $form, FormStateInterface $form_state);

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
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.bundle.info'), $container->get('entity_field.manager'));
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
  public function config($key, $value = NULL) {
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
    return array(
      'key' => FALSE,
      'direction' => SALESFORCE_MAPPING_DIRECTION_SYNC,
      'salesforce_field' => array(),
      'drupal_field_type' => $this->id,
      'drupal_field_value' => '',
      'locked' => FALSE,
      'mapping_name' => '',
    );
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
    return property_exists($this, $key) ? $this->$key : NULL;
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
    return in_array($this->config('direction'), array(SALESFORCE_MAPPING_DIRECTION_DRUPAL_SF, SALESFORCE_MAPPING_DIRECTION_SYNC));
  }

  /**
   * @return bool
   *  Whether or not this field should be pulled from Salesforce to Drupal.
   * @TODO This needs a better name. Could be mistaken for a verb.
   */
  public function pull() {
    return in_array($this->config('direction'), array(SALESFORCE_MAPPING_DIRECTION_SYNC, SALESFORCE_MAPPING_DIRECTION_SF_DRUPAL));
  }

}
