<?php

namespace Drupal\salesforce_mapping\Plugin\SalesforceMappingField;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\field\Entity\FieldConfig;

/**
 * Adapter for entity properties and fields.
 *
 * @Plugin(
 *   id = "properties",
 *   label = @Translation("Properties")
 * )
 */
class Properties extends SalesforceMappingFieldPluginBase {

  /**
   * Implementation of PluginFormInterface::buildConfigurationForm.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $pluginForm = parent::buildConfigurationForm($form, $form_state);
    // @TODO inspecting the form and form_state feels wrong, but haven't found a good way to get the entity from config before the config is saved.
    $options = $this->getConfigurationOptions($form['#entity']);

    // Display the plugin config form here:
    if (empty($options)) {
      $pluginForm['drupal_field_value'] = [
        '#markup' => t('No available properties.'),
      ];
    }
    else {
      $pluginForm['drupal_field_value'] += [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $this->config('drupal_field_value'),
        '#description' => $this->t('Select a Drupal field or property to map to a Salesforce field.<br />Entity Reference fields should be handled using Related Entity Ids or Token field types.'),
      ];
    }

    return $pluginForm;
  }

  /**
   *
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
  }

  /**
   *
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
        $values[] = $value->value;
      }
      return implode(';', $values);
    }
    else {
      return $entity->get($this->config('drupal_field_value'))->value;
    }
  }

  /**
   *
   */
  private function getConfigurationOptions(SalesforceMappingInterface $mapping) {
    $properties = $this->entityFieldManager->getFieldDefinitions(
      $mapping->get('drupal_entity_type'),
      $mapping->get('drupal_bundle')
    );

    $options = [];
    foreach ($properties as $key => $property) {
      // Entity reference fields are handled elsewhere.
      // @TODO: is this type still a thing even?
      if ($property->getType() == 'field_item:entity_reference') {
        continue;
      }
      $options[$key] = $property->getLabel();
    }
    asort($options);
    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   Field config upon which this mapping depends
   */
  public function getDependencies(SalesforceMappingInterface $mapping) {
    $field_config = FieldConfig::loadByName($mapping->get('drupal_entity_type'), $mapping->get('drupal_bundle'), $this->config('drupal_field_value'));
    if (empty($field_config)) {
      return [];
    }
    return [
      'config' => array($field_config->getConfigDependencyName()),
    ];
  }

}
