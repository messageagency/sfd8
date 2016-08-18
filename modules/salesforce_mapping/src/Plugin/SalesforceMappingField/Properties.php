<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Plugin\salesforce\SalesforceMappingField\Properties.
 */

namespace Drupal\salesforce_mapping\Plugin\SalesforceMappingField;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Field;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;

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
   * Implementation of PluginFormInterface::buildConfigurationForm
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // @TODO inspecting the form and form_state feels wrong, but haven't found a good way to get the entity from config before the config is saved.
    $options = $this->getConfigurationOptions($form['#entity']);
    if (empty($options)) {
      return array(
        '#markup' => t('No available properties.')
      );
    }
    return array(
      '#type' => 'select',
      '#options' => $options,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $this->config('drupal_field_value'),
      '#description' => $this->t('Select a Drupal field or property to map to a Salesforce field.<br />Entity Reference fields should be handled using Related Entity Ids or Token field types.'),
    );
  }

  public function value(EntityInterface $entity) {
    // No error checking here. If a property is not defined, it's a
    // configuration bug that needs to be solved elsewhere.
    return $entity->get($this->config('drupal_field_value'))->value;
  }

  private function getConfigurationOptions($mapping) {
    $properties = $this->entityFieldManager->getFieldDefinitions(
      $mapping->get('drupal_entity_type'),
      $mapping->get('drupal_bundle')
    );

    $options = array();
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

}