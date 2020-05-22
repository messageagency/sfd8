<?php

namespace Drupal\salesforce_mapping\Plugin\SalesforceMappingField;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

use Drupal\field\Entity\FieldConfig;
use Drupal\salesforce\SObject;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;
use Drupal\salesforce\Exception as SalesforceException;
use Drupal\taxonomy\Entity\Term;

/**
 * Adapter for entity Reference and fields.
 *
 * @Plugin(
 *   id = "RelatedTermString",
 *   label = @Translation("Related Term String")
 * )
 */
class RelatedTermString extends SalesforceMappingFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $pluginForm = parent::buildConfigurationForm($form, $form_state);

    // @TODO inspecting the form and form_state feels wrong, but haven't found a good way to get the entity from config before the config is saved.
    $options = $this->getConfigurationOptions($form['#entity']);

    if (empty($options)) {
      $pluginForm['drupal_field_value'] += [
        '#markup' => $this->t('No available taxonomy reference fields.'),
      ];
    }
    else {
      $pluginForm['drupal_field_value'] += [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $this->config('drupal_field_value'),
        '#description' => $this->t('Select a taxonomy reference field.<br />If more than one term is referenced, the term at delta zero will be used.<br />A taxonomy reference field will be used to sync to the term name.<br />If a term with the given string does not exist one will be created.'),
      ];
    }
    return $pluginForm;

  }

  /**
   * {@inheritdoc}
   */
  public function value(EntityInterface $entity, SalesforceMappingInterface $mapping) {
    $field_name = $this->config('drupal_field_value');
    $instances = $this->entityFieldManager->getFieldDefinitions(
      $entity->getEntityTypeId(),
      $entity->bundle()
    );

    if (empty($instances[$field_name])) {
      return;
    }

    $field = $entity->get($field_name);
    if (empty($field->getValue()) || is_null($field->entity)) {
      // This reference field is blank or the referenced entity no longer
      // exists.
      return;
    }

    // Map the term name to the salesforce field.
    return $field->entity->getName();
  }

  /**
   * {@inheritdoc}
   */
  public function pullValue(SObject $sf_object, EntityInterface $entity, SalesforceMappingInterface $mapping) {

    if (!$this->pull() || empty($this->config('salesforce_field'))) {
      throw new SalesforceException('No data to pull. Salesforce field mapping is not defined.');
    }

    $field_name = $this->config('drupal_field_value');
    $instance = FieldConfig::loadByName($this->mapping->getDrupalEntityType(), $this->mapping->getDrupalBundle(), $field_name);
    if (empty($instance)) {
      return;
    }

    $value = $sf_object->field($this->config('salesforce_field'));
    // Empty value means nothing to do here.
    if (empty($value)) {
      return NULL;
    }

    // Get the appropriate vocab from the field settings.
    $vocabs = $instance->getSetting('handler_settings')['target_bundles'];

    // Look for a term that matches the string in the salesforce field.
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('vid', $vocabs, 'IN');
    $query->condition('name', $value);
    $tids = $query->execute();

    if (!empty($tids)) {
      $term_id = reset($tids);
    }

    // If we cant find an existing term, create a new one.
    if (empty($term_id)) {
      $vocab = reset($vocabs);

      $term = Term::create([
        'name' => $value,
        'vid' => $vocab,
      ]);
      $term->save();
      $term_id = $term->id();
    }

    return $term_id;
  }

  /**
   * Helper to build form options.
   */
  private function getConfigurationOptions($mapping) {
    $instances = $this->entityFieldManager->getFieldDefinitions(
      $mapping->get('drupal_entity_type'),
      $mapping->get('drupal_bundle')
    );
    $options = [];
    foreach ($instances as $name => $instance) {
      $hand = $instance->getSetting('handler');
      // ???
      if ($hand != "default:taxonomy_term") {
        continue;
      }
      $options[$name] = $instance->getLabel();
    }
    asort($options);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    $definition = parent::getPluginDefinition();
    // Add reference field.
    if ($field = FieldConfig::loadByName($this->mapping->getDrupalEntityType(), $this->mapping->getDrupalBundle(), $this->config('drupal_field_value'))) {
      $definition['config_dependencies']['config'][] = $field->getConfigDependencyName();
      // Add dependencies of referenced field.
      foreach ($field->getDependencies() as $type => $dependency) {
        foreach ($dependency as $item) {
          $definition['config_dependencies'][$type][] = $item;
        }
      }
    }
    return $definition;
  }

  /**
   * {@inheritdoc}
   */
  public function checkFieldMappingDependency(array $dependencies) {
    $definition = $this->getPluginDefinition();
    foreach ($definition['config_dependencies'] as $type => $dependency) {
      foreach ($dependency as $item) {
        if (!empty($dependencies[$type][$item])) {
          return TRUE;
        }
      }
    }
  }

}
