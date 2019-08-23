<?php

namespace Drupal\salesforce_mapping;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Event\SalesforceWarningEvent;
use Drupal\salesforce\Exception as SalesforceException;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SObject;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use DateTime;

/**
 * Defines a base Salesforce Mapping Field Plugin implementation.
 *
 * Extenders need to implement SalesforceMappingFieldPluginInterface::value()
 * and PluginFormInterface::buildConfigurationForm().
 *
 * @see \Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface
 * @see \Drupal\Core\Plugin\PluginFormInterface
 */
abstract class SalesforceMappingFieldPluginBase extends PluginBase implements SalesforceMappingFieldPluginInterface, PluginFormInterface, ConfigurablePluginInterface, ContainerFactoryPluginInterface {

  /**
   * The label of the mapping.
   *
   * @var string
   */
  protected $label;

  /**
   * The machine name of the mapping.
   *
   * @var string
   */
  protected $id;

  /**
   * Entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Salesforce client service.
   *
   * @var \Drupal\salesforce\Rest\RestClientInterface
   */
  protected $salesforceClient;

  /**
   * Storage handler for SF mappings.
   *
   * @var \Drupal\salesforce_mapping\SalesforceMappingStorage
   */
  protected $mappingStorage;

  /**
   * Storage handler for Mapped Objects.
   *
   * @var \Drupal\salesforce_mapping\MappedObjectStorage
   */
  protected $mappedObjectStorage;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * SalesforceMappingFieldPluginBase constructor.
   *
   * @param array $configuration
   *   Plugin config.
   * @param string $plugin_id
   *   Plugin id.
   * @param array $plugin_definition
   *   Plugin definition.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   Entity type bundle info service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   Entity field manager.
   * @param \Drupal\salesforce\Rest\RestClientInterface $rest_client
   *   Salesforce client.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   Entity manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   ETM service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   Date formatter service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityFieldManagerInterface $entity_field_manager, RestClientInterface $rest_client, EntityManagerInterface $entity_manager, EntityTypeManagerInterface $etm, DateFormatterInterface $dateFormatter, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityFieldManager = $entity_field_manager;
    $this->salesforceClient = $rest_client;
    $this->entityManager = $entity_manager;
    $this->entityTypeManager = $etm;
    $this->mappingStorage = $entity_manager->getStorage('salesforce_mapping');
    $this->mappedObjectStorage = $entity_manager->getStorage('salesforce_mapped_object');
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
        $date = new DrupalDateTime($value, 'UTC');
        $value = $date->format(DateTime::ISO8601);
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

    $data_definition = $this->getFieldDataDefinition($entity);
    $drupal_field_type = $this->getDrupalFieldType($data_definition);
    $drupal_field_settings = $data_definition->getSettings();

    $field_definition = $describe->getField($this->config('salesforce_field'));
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
   *
   * @return array|string
   *   The config value.
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
      'mapping_id' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $pluginForm = [];
    $plugin_def = $this->getPluginDefinition();

    // Extending plugins will probably inject most of their own logic here:
    $pluginForm['drupal_field_value'] = [
      '#title' => $plugin_def['label'],
    ];

    $options = $this->getSalesforceFieldOptions($form['#entity']->getSalesforceObjectType());
    $pluginForm['salesforce_field'] = [
      '#title' => t('Salesforce field'),
      '#type' => 'select',
      '#description' => t('Select a Salesforce field to map.'),
      '#options' => $options,
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
   * Helper for buildConfigurationForm() to build a broken field plugin.
   *
   * @see buildConfigurationForm()
   *
   * @return array
   *   The dummy form with message to indicate the plugin is broken.
   */
  protected function buildBrokenConfigurationForm(array &$pluginForm, FormStateInterface $form_state) {
    foreach ($this->config() as $key => $value) {
      if (!empty($pluginForm[$key])) {
        $pluginForm[$key]['#type'] = 'hidden';
        $pluginForm[$key]['#value'] = $value;
      }
    }

    $pluginForm['drupal_field_type'] = [
      '#type' => 'hidden',
      '#value' => $this->config('drupal_field_type'),
    ];

    return [
      'message' => [
        '#markup' => '<div class="error">'
        . $this->t('The field plugin %plugin is broken or missing.', ['%plugin' => $this->config('drupal_field_type')])
        . '</div>',
      ],
    ];
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
   * {@inheritdoc}
   */
  public function calculateDependencies() {

  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->get('label');
  }

  /**
   * {@inheritdoc}
   */
  public function get($key) {
    return $this->config($key);
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    $this->$key = $value;
  }

  /**
   * {@inheritdoc}
   */
  public function push() {
    return in_array($this->config('direction'), [
      MappingConstants::SALESFORCE_MAPPING_DIRECTION_DRUPAL_SF,
      MappingConstants::SALESFORCE_MAPPING_DIRECTION_SYNC,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function pull() {
    return in_array($this->config('direction'), [
      MappingConstants::SALESFORCE_MAPPING_DIRECTION_SYNC,
      MappingConstants::SALESFORCE_MAPPING_DIRECTION_SF_DRUPAL,
    ]);
  }

  /**
   * Helper to retreive a list of fields for a given object type.
   *
   * @param string $sfobject_name
   *   The object type of whose fields you want to retreive.
   *
   * @return array
   *   An array of values keyed by machine name of the field with the label as
   *   the value, formatted to be appropriate as a value for #options.
   */
  protected function getSalesforceFieldOptions($sfobject_name) {
    // Static cache since this function is called frequently across many
    // different object instances.
    $options = &drupal_static(__CLASS__ . __FUNCTION__, []);
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

  /**
   * Return TRUE if the given field uses an entity reference handler.
   *
   * @param \Drupal\Core\Field\FieldDefinitionInterface $instance
   *   The field.
   *
   * @return bool
   *   Whether the field is an entity reference.
   */
  protected function instanceOfEntityReference(FieldDefinitionInterface $instance) {
    $handler = $instance->getSetting('handler');
    // We must have a handler.
    if (empty($handler)) {
      return FALSE;
    }
    // If the handler is a selection interface, return TRUE.
    $plugin = $this->selectionPluginManager()->getSelectionHandler($instance);
    return $plugin instanceof SelectionInterface;
  }

  /**
   * Wraper for plugin.manager.entity_reference_selection service.
   *
   * @return \Drupal\Core\Entity\EntityAutocompleteMatcher
   *   Entity autocompleter service.
   */
  protected function selectionPluginManager() {
    return \Drupal::service('plugin.manager.entity_reference_selection');
  }

  /**
   * Helper method to get the Data Definition for the current field.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The Entity to get the field from.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface
   *   The Data Definition of the current field.
   */
  protected function getFieldDataDefinition(EntityInterface $entity) {
    return $entity->get($this->config('drupal_field_value'))
      ->getFieldDefinition()
      ->getItemDefinition();
  }

  /**
   * Helper method to get the Field Type of the given Field Data Definition.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface $data_definition
   *   The Drupal Field Data Definition object.
   *
   * @return string|null
   *   The Drupal Field Type or NULL.
   */
  protected function getDrupalFieldType(DataDefinitionInterface $data_definition) {
    $field_main_property = $data_definition
      ->getPropertyDefinition($data_definition->getMainPropertyName());

    return $field_main_property ? $field_main_property->getDataType() : NULL;
  }

}
