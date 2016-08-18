<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Plugin\SalesforceMappingField\RelatedProperties.
 */

namespace Drupal\salesforce_mapping\Plugin\SalesforceMappingField;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Field;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;

/**
 * Adapter for entity Reference and fields.
 *
 * @Plugin(
 *   id = "RelatedProperties",
 *   label = @Translation("Related Entity Properties")
 * )
 */
class RelatedProperties extends SalesforceMappingFieldPluginBase {

  /**
   * Implementation of PluginFormInterface::buildConfigurationForm
   * This is basically the inverse of Properties::buildConfigurationForm()
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // @TODO inspecting the form and form_state feels wrong, but haven't found a good way to get the entity from config before the config is saved.
    $options = $this->getConfigurationOptions($form['#entity']);
    if (empty($options)) {
      return array(
        '#markup' => t('No available entity reference fields.')
      );
    }
    return array(
      '#type' => 'select',
      '#options' => $options,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $this->config('drupal_field_value'),
      '#description' => $this->t('Select a property from the referenced field.<br />If more than one entity is referenced, the entity at delta zero will be used.<br />An entity reference field will be used to sync an identifier, e.g. Salesforce ID and Node ID.'),
    );
  }

  public function value(EntityInterface $entity) {
    list($field_name, $referenced_field_name) = explode(':', $this->config('drupal_field_value'), 2);
    // Since we're not setting hard restrictions around bundles/fields, we may
    // have a field that doesn't exist for the given bundle/entity. In that
    // case, calling get() on an entity with a non-existent field argument
    // causes an exception during entity save. Probably a bug, but I haven't
    // found it in the issue queue. So, just check first to make sure the field
    // exists.
    $instances = Field::fieldInfo()->getBundleInstances(
      get_class($entity),
      $entity->bundle()
    );
    if (empty($instances[$field_name])) {
      return;
    }

    $field = $entity->get($field_name);
    if (empty($field->value)) {
      // This reference field is blank
      return;
    }

    // Now we can actually fetch the referenced entity.
    $field_settings = $field->getFieldDefinition()->getFieldSettings();
    try {
      $referenced_entity = \Drupal::entityTypeManager()
        ->getStorage($field_settings['target_type'])
        ->load($field->value);
    }
    catch (Exception $e) {
      // @TODO something about this exception
      return;
    }

    // Again, try to avoid some complicated fatal further downstream.
    $referenced_instances = $this->entityFieldManager->getFieldDefinitions(
      get_class($referenced_entity),
      $referenced_entity->bundle()
    );
    if (empty($referenced_instances[$referenced_field_name])) {
      return;
    }
    return $referenced_entity->get($referenced_field_name)->value;
  }

  private function getConfigurationOptions($mapping) {
    $instances = $this->entityFieldManager->getFieldDefinitions(
      $mapping->get('drupal_entity_type'),
      $mapping->get('drupal_bundle')
    );
    if (empty($instances)) {
      return;
    }

    $options = array();

    // Loop over every field on the mapped entity. For reference fields, expose
    // all properties of the referenced entity.
    $fieldMap = $this->entityFieldManager->getFieldMap();
    foreach ($instances as $instance) {
      // @TODO replace this with EntityFieldManagerInterface::getFieldMapByFieldType
      if ($instance->getType() != 'entity_reference') {
        continue;
      }

      $settings = $instance->getSettings();
      // We must have an entity type.

      if (empty($settings['target_type'])) {
        continue;
      }

      $entity_type = $settings['target_type'];
      $properties = array();

      // If handler is default and allowed bundles are set, include all fields 
      // from all allowed bundles.
      try {
        if (!empty($settings['handler_settings']['target_bundles'])) {
          foreach ($settings['handler_settings']['target_bundles'] as $bundle) {
            $properties += $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
          }
        }
        else {
          $properties += $this->entityFieldManager->getBaseFieldDefinitions($entity_type);
        }
      }
      catch (\LogicException $e) {
        // @TODO is there a better way to exclude non-fieldables?
        continue;
      }

      foreach ($properties as $key => $property) {
        $options[$instance->getLabel()][$instance->getName().':'.$key] = $property->getLabel();
      }
    }

    if (empty($options)) {
      return;
    }

    // Alphabetize options for UI
    foreach ($options as $group => &$option_set) {
      asort($option_set);
    }
    asort($options);
    return $options;
  }

}