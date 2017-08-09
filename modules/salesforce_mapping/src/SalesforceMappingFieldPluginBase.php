<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Plugin\SalesforceMappingFieldPluginBase.
 */

namespace Drupal\salesforce_mapping;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Event\SalesforceWarningEvent;
use Drupal\salesforce\Exception as SalesforceException;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SObject;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\MappedObjectStorage;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface;
use Drupal\salesforce_mapping\SalesforceMappingStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

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
   * @var \Drupal\salesforce_mapping\Entity\SalesforceMappingStorage
   */
  protected $mapping_storage;

  /**
   * Storage handler for Mapped Objects
   *
   * @var \Drupal\salesforce_mapping\MappedObjectStorage
   */
  protected $mapped_object_storage;

  protected $entityTypeManager;

  protected $eventDispatcher;

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
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityFieldManagerInterface $entity_field_manager, RestClientInterface $rest_client, EntityManagerInterface $entity_manager, EntityTypeManagerInterface $etm, DateFormatterInterface $dateFormatter, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
    $this->salesforceClient = $rest_client;
    $this->entityManager = $entity_manager;
    $this->entityTypeManager = $etm;
    $this->mapping_storage = $entity_manager->getStorage('salesforce_mapping');
    $this->mapped_object_storage = $entity_manager->getStorage('salesforce_mapped_object');
    $this->dateFormatter = $dateFormatter;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager'),
      $container->get('salesforce.client'),
      $container->get('entity.manager'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('event_dispatcher')
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
  public function pushValue(EntityInterface $entity, SalesforceMappingInterface $mapping) {
    // @TODO to provide for better extensibility, this would be better implemented as some kind of constraint or plugin system. That would also open new possibilities for injecting business logic into he mapping layer.

    // If this field plugin doesn't support salesforce_field config type, or
    // doesn't do push, then return the raw value from the mapped entity.
    $value = $this->value($entity, $mapping);
    if (!$this->push() || empty($this->config('salesforce_field'))) {
      return $value;
    }

    // objectDescribe can throw an exception, but that's outside the scope of
    // being handled here. Allow it to percolate.
    $describe = $this
      ->salesforceClient
      ->objectDescribe($mapping->getSalesforceObjectType());

    try {
      $field_definition = $describe->getField($this->config('salesforce_field'));
    }
    catch (\Exception $e) {
      $this->eventDispatcher->dispatch(SalesforceEvents::WARNING, new SalesforceWarningEvent($e, 'Field definition not found for %describe.%field', ['%describe' => $describe->getName(), '%field' => $this->config('salesforce_field')]));
      // If getField throws, however, just return the raw value.
      return $value;
    }

    switch (strtolower($field_definition['type'])) {
      case 'boolean':
        if ($value == 'false') {
          $value = FALSE;
        }
        $value = (bool) $value;
        break;

      case 'date':
      case 'datetime':
        $tmp = $value;
        if (!is_int($tmp)) {
          $tmp = strtotime($tmp);
        }
        if (!empty($tmp)) {
          $value = $this->dateFormatter->format($tmp, 'custom', 'c');
        }
        break;

      case 'double':
        $value = (double) $value;
        break;

      case 'integer':
        $value = (int) $value;
        break;

      case 'multipicklist':
        if (is_array($value)) {
          $value = implode(';', $value);
        }
        break;

      case 'id':
      case 'reference':
        if (empty($value)) {
          break;
        }
        // If value is an SFID, cast to string.
        if ($value instanceof SFID) {
          $value = (string) $value;
        }
        // Otherwise, send it through SFID constructor & cast to validate.
        else {
          $value = (string) (new SFID($value));
        }
        break;
    }

    if ($field_definition['length'] > 0 && strlen($value) > $field_definition['length']) {
      $value = substr($value, 0, $field_definition['length']);
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function pullValue(SObject $sf_object, EntityInterface $entity, SalesforceMappingInterface $mapping) {
    // @TODO to provide for better extensibility, this would be better implemented as some kind of constraint or plugin system. That would also open new possibilities for injecting business logic into he mapping layer.

    if (!$this->pull() || empty($this->config('salesforce_field'))) {
      throw new SalesforceException('No data to pull. Salesforce field mapping is not defined.');
    }

    $value = $sf_object->field($this->config('salesforce_field'));

    // objectDescribe can throw an exception, but that's outside the scope of
    // being handled here. Allow it to percolate.
    $describe = $this
      ->salesforceClient
      ->objectDescribe($mapping->getSalesforceObjectType());

    $field_definition = $describe->getField($this->config('salesforce_field'));

    $drupal_field_definition = $entity->get($this->config('drupal_field_value'))
      ->getFieldDefinition()
      ->getItemDefinition();
    // @TODO this will need to be rewritten for https://www.drupal.org/node/2899460
    $drupal_field_type = $drupal_field_definition
      ->getPropertyDefinition($drupal_field_definition->getMainPropertyName())
      ->getDataType();
    $drupal_field_settings = $drupal_field_definition->getSettings();

    switch (strtolower($field_definition['type'])) {
      case 'boolean':
        if (is_string($value) && strtolower($value) === 'false') {
          $value = FALSE;
        }
        $value = (bool) $value;
        break;

      case 'datetime':
        if ($drupal_field_type === 'datetime_iso8601') {
          $value = substr($value, 0, 19);
        }
        break;

      case 'double':
        $value = (double) $value;
        break;

      case 'integer':
        $value = (int) $value;
        break;

      case 'multipicklist':
        if (!is_array($value)) {
          $value = explode(';', $value);
          $value = array_map('trim', $value);
        }
        break;

      case 'id':
      case 'reference':
        if (empty($value)) {
          break;
        }
        // If value is an SFID, cast to string.
        if ($value instanceof SFID) {
          $value = (string) $value;
        }
        // Otherwise, send it through SFID constructor & cast to validate.
        else {
          $value = (string) (new SFID($value));
        }
        break;

      default:
        if (is_string($value)) {
          if (isset($drupal_field_settings['max_length']) && $drupal_field_settings['max_length'] > 0 && $drupal_field_settings['max_length'] < strlen($value)) {
            $value = substr($value, 0, $drupal_field_settings['max_length']);
          }
        }
        break;

    }

    return $value;
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
      'direction' => MappingConstants::SALESFORCE_MAPPING_DIRECTION_SYNC,
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
        MappingConstants::SALESFORCE_MAPPING_DIRECTION_DRUPAL_SF => t('Drupal to SF'),
        MappingConstants::SALESFORCE_MAPPING_DIRECTION_SF_DRUPAL => t('SF to Drupal'),
        MappingConstants::SALESFORCE_MAPPING_DIRECTION_SYNC => t('Sync'),
      ],
      '#required' => TRUE,
      '#default_value' => $this->config('direction') ? $this->config('direction') : MappingConstants::SALESFORCE_MAPPING_DIRECTION_SYNC,
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
    return in_array($this->config('direction'), [
      MappingConstants::SALESFORCE_MAPPING_DIRECTION_DRUPAL_SF,
      MappingConstants::SALESFORCE_MAPPING_DIRECTION_SYNC
    ]);
  }

  /**
   * @return bool
   *  Whether or not this field should be pulled from Salesforce to Drupal.
   * @TODO This needs a better name. Could be mistaken for a verb.
   */
  public function pull() {
    return in_array($this->config('direction'), [
      MappingConstants::SALESFORCE_MAPPING_DIRECTION_SYNC,
      MappingConstants::SALESFORCE_MAPPING_DIRECTION_SF_DRUPAL
    ]);
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

  /**
   * {@inheritdoc}
   */
  public function getDependencies(SalesforceMappingInterface $mapping) {
    return [];
  }

}
