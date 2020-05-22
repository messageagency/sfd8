<?php

namespace Drupal\salesforce_webform\Plugin\SalesforceMappingField;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;
use Drupal\salesforce_mapping\MappingConstants;

/**
 * Adapter for Webform elements.
 *
 * @Plugin(
 *   id = "WebformEntityElements",
 *   label = @Translation("Webform Entity Elements")
 * )
 */
class WebformEntityElements extends SalesforceMappingFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function getDependencies(SalesforceMappingInterface $mapping) {
    return ['module' => ['webform']];
  }

  /**
   * {@inheritdoc}
   */
  public static function isAllowed(SalesforceMappingInterface $mapping) {
    return \Drupal::service('module_handler')->moduleExists('webform');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $pluginForm = parent::buildConfigurationForm($form, $form_state);

    $options = $this->getConfigurationOptions($form['#entity']);

    if (empty($options)) {
      $pluginForm['drupal_field_value'] += [
        '#markup' => $this->t('No available webform entity reference elements.'),
      ];
    }
    else {
      $pluginForm['drupal_field_value'] += [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $this->config('drupal_field_value'),
        '#description' => $this->t('Select a webform entity reference element.'),
      ];
    }
    // Just allowed to push.
    $pluginForm['direction']['#options'] = [
      MappingConstants::SALESFORCE_MAPPING_DIRECTION_DRUPAL_SF => $pluginForm['direction']['#options'][MappingConstants::SALESFORCE_MAPPING_DIRECTION_DRUPAL_SF],
    ];
    $pluginForm['direction']['#default_value'] =
      MappingConstants::SALESFORCE_MAPPING_DIRECTION_DRUPAL_SF;
    return $pluginForm;

  }

  /**
   * {@inheritdoc}
   */
  public function value(EntityInterface $entity, SalesforceMappingInterface $mapping) {
    $element_parts = explode('__', $this->config('drupal_field_value'));
    $main_element_name = reset($element_parts);
    $webform = $this->entityTypeManager->getStorage('webform')->load($mapping->get('drupal_bundle'));
    $webform_element = $webform->getElement($main_element_name);
    if (!$webform_element) {
      // This reference field does not exist.
      return;
    }

    try {

      $value = $entity->getElementData($main_element_name);

      $mappedObjects = $this->mappedObjectStorage->loadByDrupal($webform_element['#target_type'], $value);
      if (!empty($mappedObjects)) {
        $mappedObject = reset($mappedObjects);
        return $mappedObject->sfid();
      }
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * Form options helper.
   */
  protected function getConfigurationOptions($mapping) {
    /** @var \Drupal\webform\Entity\Webform $webform */
    $webform = $this->entityTypeManager->getStorage('webform')->load($mapping->get('drupal_bundle'));
    $webform_elements = $webform->getElementsInitializedFlattenedAndHasValue();
    if (empty($webform_elements)) {
      return;
    }

    $options = [];

    foreach ($webform_elements as $element_id => $element) {
      // All webform elements that are entity references are named in the form:
      // webform_entity_[elementtype]. Except autocomplete which is missing
      // "webform".
      if (stripos($element['#type'], 'webform_entity') !== FALSE || $element['#type'] == 'entity_autocomplete') {
        $options[$element_id] = $element['#title'];
      }
    }

    if (empty($options)) {
      return;
    }

    asort($options);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function pull() {
    return FALSE;
  }

}
