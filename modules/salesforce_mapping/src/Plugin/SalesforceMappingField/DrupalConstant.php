<?php

namespace Drupal\salesforce_mapping\Plugin\SalesforceMappingField;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\salesforce\SObject;

/**
 * Adapter for entity Constant and fields.
 *
 * @Plugin(
 *   id = "DrupalConstant",
 *   label = @Translation("Drupal Constant")
 * )
 */
class DrupalConstant extends SalesforceMappingFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $pluginForm = parent::buildConfigurationForm($form, $form_state);

    // @TODO inspecting the form and form_state feels wrong, but haven't found a good way to get the entity from config before the config is saved.
    $options = $this->getConfigurationOptions($form['#entity']);

    // Display the plugin config form here:
    if (empty($options)) {
      $pluginForm['drupal_field_value'] = [
        '#markup' => $this->t('No available properties.'),
      ];
    }
    else {
      $pluginForm['drupal_field_value'] += [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $this->config('drupal_field_value'),
        '#description' => $this->t('Select a Drupal field or property to map to a constant.'),
      ];
    }

    // A field to hold the constant value.
    $pluginForm['drupal_constant'] = [
      '#type' => 'textfield',
      '#default_value' => $this->config('drupal_constant'),
      '#description' => $this->t('Enter a constant value to map to a Drupal field.'),
    ];
    // There is no salesforce field for this mapping.
    unset($pluginForm['salesforce_field']);

    // We should only be able to pull a constant value to a Drupal field.
    $pluginForm['direction']['#options'] = [
      MappingConstants::SALESFORCE_MAPPING_DIRECTION_SF_DRUPAL => $pluginForm['direction']['#options'][MappingConstants::SALESFORCE_MAPPING_DIRECTION_SF_DRUPAL],
    ];
    $pluginForm['direction']['#default_value'] =
      MappingConstants::SALESFORCE_MAPPING_DIRECTION_SF_DRUPAL;

    return $pluginForm;

  }

  /**
   * Form options helper.
   */
  private function getConfigurationOptions(SalesforceMappingInterface $mapping) {
    $instances = $this->entityFieldManager->getFieldDefinitions(
      $mapping->get('drupal_entity_type'),
      $mapping->get('drupal_bundle')
    );

    $options = [];
    foreach ($instances as $key => $instance) {
      // Entity reference fields are handled elsewhere.
      if ($this->instanceOfEntityReference($instance)) {
        continue;
      }
      $options[$key] = $instance->getLabel();
    }
    asort($options);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function value(EntityInterface $entity, SalesforceMappingInterface $mapping) {

  }

  /**
   * {@inheritdoc}
   */
  public function pullValue(SObject $sf_object, EntityInterface $entity, SalesforceMappingInterface $mapping) {
    return $this->config('drupal_constant');
  }

  /**
   * {@inheritdoc}
   */
  public function push() {
    return FALSE;
  }

}
