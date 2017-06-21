<?php

namespace Drupal\salesforce_mapping\Plugin\SalesforceMappingField;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\field\Field;
use Drupal\salesforce\Event\SalesforceErrorEvent;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;
use Drupal\salesforce\Event\SalesforceEvents;

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
    $pluginForm = parent::buildConfigurationForm($form, $form_state);

    // @TODO inspecting the form and form_state feels wrong, but haven't found a good way to get the entity from config before the config is saved.
    $options = $this->getConfigurationOptions($form['#entity']);

    if (empty($options)) {
      $pluginForm['drupal_field_value'] += [
        '#markup' => t('No available entity reference fields.'),
      ];
    }
    else {
      $pluginForm['drupal_field_value'] += [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $this->config('drupal_field_value'),
        '#description' => $this->t('Select a property from the referenced field.<br />If more than one entity is referenced, the entity at delta zero will be used.<br />An entity reference field will be used to sync an identifier, e.g. Salesforce ID and Node ID.'),
      ];
    }
    return $pluginForm;

  }

  /**
   *
   */
  public function value(EntityInterface $entity, SalesforceMappingInterface $mapping) {
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
      // This reference field is blank.
      return;
    }

    // Now we can actually fetch the referenced entity.
    $field_settings = $field->getFieldDefinition()->getFieldSettings();
    try {
      $referenced_entity = $this
        ->entityTypeManager
        ->getStorage($field_settings['target_type'])
        ->load($field->value);
    }
    catch (\Exception $e) {
      // @TODO something about this exception
      \Drupal::service('event_dispatcher')->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e));
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

  /**
   *
   */
  private function getConfigurationOptions($mapping) {
    $instances = $this->entityFieldManager->getFieldDefinitions(
      $mapping->get('drupal_entity_type'),
      $mapping->get('drupal_bundle')
    );
    if (empty($instances)) {
      return;
    }

    $options = [];

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
      $properties = [];

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
        $options[$instance->getLabel()][$instance->getName() . ':' . $key] = $property->getLabel();
      }
    }

    if (empty($options)) {
      return;
    }

    // Alphabetize options for UI.
    foreach ($options as $group => &$option_set) {
      asort($option_set);
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
    $deps = [];
    list($field_name, $referenced_field_name) = explode(':', $this->config('drupal_field_value'), 2);
    $field_config = FieldConfig::loadByName($mapping->get('drupal_entity_type'), $mapping->get('drupal_bundle'), $field_name);
    if (empty($field_config)) {
      return $deps;
    }
    $deps[] = [
      'config' => array($field_config->getConfigDependencyName()),
    ];
    $field_settings = $field_config->getSettings();

    if (empty($field_settings['target_type'])) {
      return $deps;
    }

    $fields = $this->entityFieldManager->getBaseFieldDefinitions($field_settings['target_type']);
    if (empty($fields[$referenced_field_name])) {
      return $deps;
    }

    $deps['config'][] = $fields[$referenced_field_name]->getConfigDependencyName();

    return $deps;
  }

}
