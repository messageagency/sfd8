<?php

namespace Drupal\salesforce_mapping\Plugin\SalesforceMappingField;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\SObject;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Adapter for entity properties and fields.
 *
 * @Plugin(
 *   id = "properties_extended",
 *   label = @Translation("Properties, Extended")
 * )
 */
class PropertiesExtended extends SalesforceMappingFieldPluginBase {

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Data fetcher service.
   *
   * @var \Drupal\typed_data\DataFetcherInterface
   */
  protected $dataFetcher;

  /**
   * PropertiesExtended constructor.
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
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Module handler service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityFieldManagerInterface $entity_field_manager, RestClientInterface $rest_client, EntityManagerInterface $entity_manager, EntityTypeManagerInterface $etm, DateFormatterInterface $dateFormatter, EventDispatcherInterface $event_dispatcher, ModuleHandlerInterface $moduleHandler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_bundle_info, $entity_field_manager, $rest_client, $entity_manager, $etm, $dateFormatter, $event_dispatcher);
    $this->moduleHandler = $moduleHandler;
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
      $container->get('event_dispatcher'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDependencies(SalesforceMappingInterface $mapping) {
    return ['module' => ['typed_data']];
  }

  /**
   * {@inheritdoc}
   */
  public static function isAllowed(SalesforceMappingInterface $mapping) {
    return \Drupal::service('module_handler')->moduleExists('typed_data');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $pluginForm = parent::buildConfigurationForm($form, $form_state);
    if (!$this->moduleHandler->moduleExists('typed_data')) {
      $this->messenger()->addError('Install Typed Data module to use Extended Properties fields.');
      return $this->buildBrokenConfigurationForm($pluginForm, $form_state);
    }
    $mapping = $form['#entity'];

    // Display the plugin config form here:
    $context_name = 'drupal_field_value';

    // If the form has been submitted already, take the mode from the submitted
    // values, otherwise default to existing configuration. And if that does not
    // exist default to the "input" mode.
    $mode = $form_state->get('context_' . $context_name);
    if (!$mode) {
      $mode = 'selector';
      $form_state->set('context_' . $context_name, $mode);
    }
    $title = $mode == 'selector' ? $this->t('Data selector') : $this->t('Value');

    $pluginForm[$context_name]['setting'] = [
      '#type' => 'textfield',
      '#title' => $title,
      '#attributes' => ['class' => ['drupal-field-value']],
      '#default_value' => $this->config('drupal_field_value'),
    ];
    $element = &$pluginForm[$context_name]['setting'];
    if ($mode == 'selector') {
      $element['#description'] = $this->t("The data selector helps you drill down into the data available.");
      $element['#autocomplete_route_name'] = 'salesforce_mapping.autocomplete_controller_autocomplete';
      $element['#autocomplete_route_parameters'] = ['entity_type_id' => $mapping->get('drupal_entity_type'), 'bundle' => $mapping->get('drupal_bundle')];
    }
    $value = $mode == 'selector' ? $this->t('Switch to the direct input mode') : $this->t('Switch to data selection');
    $pluginForm[$context_name]['switch_button'] = [
      '#type' => 'submit',
      '#name' => 'context_' . $context_name,
      '#attributes' => ['class' => ['drupal-field-switch-button']],
      '#parameter' => $context_name,
      '#value' => $value,
      '#submit' => [static::class . '::switchContextMode'],
      // Do not validate!
      '#limit_validation_errors' => [],
    ];

    return $pluginForm;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $vals = $form_state->getValues();
    $config = $vals['config'];
    if (empty($config['salesforce_field'])) {
      $form_state->setError($form['config']['salesforce_field'], t('Salesforce field is required.'));
    }
    if (empty($config['drupal_field_value'])) {
      $form_state->setError($form['config']['drupal_field_value'], t('Drupal field is required.'));
    }
    // @TODO: Should we validate the $config['drupal_field_value']['setting'] property?
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    // Resetting the `drupal_field_value` to just the `setting` portion,
    // which should be a string.
    $config_value = $form_state->getValue('config');
    $config_value['drupal_field_value'] = $config_value['drupal_field_value']['setting'];
    $form_state->setValue('config', $config_value);
  }

  /**
   * {@inheritdoc}
   */
  public function value(EntityInterface $entity, SalesforceMappingInterface $mapping) {
    // No error checking here. If a property is not defined, it's a
    // configuration bug that needs to be solved elsewhere.
    // Multipicklist is the only target type that handles multi-valued fields.
    $describe = $this
      ->salesforceClient
      ->objectDescribe($mapping->getSalesforceObjectType());
    $field_definition = $describe->getField($this->config('salesforce_field'));
    if ($field_definition['type'] == 'multipicklist') {
      $values = [];
      foreach ($entity->get($this->config('drupal_field_value')) as $value) {
        $values[] = $this->getStringValue($entity, $value);
      }
      return implode(';', $values);
    }
    else {
      return $this->getStringValue($entity, $this->config('drupal_field_value'));
    }
  }

  /**
   * Data Fetcher wrapper.
   *
   * @return \Drupal\typed_data\DataFetcherInterface
   *   Data fetcher service.
   *
   * @throws \Exception
   *   If typed_data.data_fetcher service does not exist.
   */
  protected function getDataFetcher() {
    if (!\Drupal::hasService('typed_data.data_fetcher')) {
      throw new \Exception('Module typed_data must be installed to use Extended Properties');
    }
    if (empty($this->dataFetcher)) {
      $this->dataFetcher = \Drupal::service('typed_data.data_fetcher');
    }
    return $this->dataFetcher;
  }

  /**
   * {@inheritdoc}
   */
  public function pullValue(SObject $sf_object, EntityInterface $entity, SalesforceMappingInterface $mapping) {
    $field_selector = $this->config('drupal_field_value');
    $pullValue = parent::pullValue($sf_object, $entity, $mapping);
    try {
      // Fetch the TypedData property and set its value.
      $data = $this->getDataFetcher()->fetchDataByPropertyPath($entity->getTypedData(), $field_selector);
      $data->setValue($pullValue);
      return $data;
    }
    catch (MissingDataException $e) {

    }
    catch (\Drupal\typed_data\Exception\InvalidArgumentException $e) {

    }
    // Allow any other exception types to percolate.
    // If the entity doesn't have any value in the field, data fetch will
    // throw an exception. We must attempt to create the field.
    // Typed Data API doesn't provide any good way to initialize a field value
    // given a selector. Instead we have to do it ourselves.
    // We descend only to the first-level fields on the entity. Cascading pull
    // values to entity references is not supported.
    $parts = explode('.', $field_selector, 4);

    switch (count($parts)) {
      case 1:
        $entity->set($field_selector, $pullValue);
        return $entity->getTypedData()->get($field_selector);

      case 2:
        $field_name = $parts[0];
        $delta = 0;
        $property = $parts[1];
        break;

      case 3:
        $field_name = $parts[0];
        $delta = $parts[1];
        $property = $parts[2];
        if (!is_numeric($delta)) {
          return;
        }
        break;

      case 4:
        return;

    }

    /** @var \Drupal\Core\TypedData\ListInterface $list_data */
    $list_data = $entity->get($field_name);
    // If the given delta has not been initialized, initialize it.
    if (!$list_data->get($delta) instanceof TypedDataInterface) {
      $list_data->set($delta, []);
    }

    /** @var \Drupal\Core\TypedData\TypedDataInterface|\Drupal\Core\TypedData\ComplexDataInterface $typed_data */
    $typed_data = $list_data->get($delta);
    if ($typed_data instanceof ComplexDataInterface && $property) {
      // If the given property has not been initialized, initialize it.
      if (!$typed_data->get($property) instanceof TypedDataInterface) {
        $typed_data->set($property, []);
      }
      /** @var \Drupal\Core\TypedData\TypedDataInterface $typed_data */
      $typed_data = $typed_data->get($property);
    }

    if (!$typed_data instanceof TypedDataInterface) {
      return;
    }
    $typed_data->setValue($pullValue);
    return $typed_data->getParent();
  }

  /**
   * Helper Method to check for and retrieve field data.
   *
   * If it is just a regular field/property of the entity, the data is
   * retrieved with ->value(). If this is a property referenced using the
   * typed_data module's extension, use typed_data module's DataFetcher class
   * to retrieve the value.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to search the Typed Data for.
   * @param string $drupal_field_value
   *   The Typed Data property to get.
   *
   * @return string
   *   The String representation of the Typed Data property value.
   */
  protected function getStringValue(EntityInterface $entity, $drupal_field_value) {
    try {
      return $this->getDataFetcher()->fetchDataByPropertyPath($entity->getTypedData(), $drupal_field_value)->getString();
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldDataDefinition(EntityInterface $entity) {
    $data_definition = $this->getDataFetcher()->fetchDefinitionByPropertyPath($entity->getTypedData()->getDataDefinition(), $this->config('drupal_field_value'));
    if ($data_definition instanceof ListDataDefinitionInterface) {
      $data_definition = $data_definition->getItemDefinition();
    }

    return $data_definition;
  }

  /**
   * {@inheritdoc}
   */
  protected function getDrupalFieldType(DataDefinitionInterface $data_definition) {
    $field_main_property = $data_definition;
    if ($data_definition instanceof ComplexDataDefinitionInterface) {
      $field_main_property = $data_definition
        ->getPropertyDefinition($data_definition->getMainPropertyName());
    }

    return $field_main_property ? $field_main_property->getDataType() : NULL;
  }

  /**
   * Submit callback: switch a context to data selector or direct input mode.
   */
  public static function switchContextMode(array &$form, FormStateInterface $form_state) {
    $element_name = $form_state->getTriggeringElement()['#name'];
    $mode = $form_state->get($element_name);
    $switched_mode = $mode == 'selector' ? 'input' : 'selector';
    $form_state->set($element_name, $switched_mode);
    $form_state->setRebuild();
  }

}
