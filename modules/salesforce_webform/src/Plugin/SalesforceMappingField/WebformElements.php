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
 *   id = "WebformElements",
 *   label = @Translation("Webform elements")
 * )
 */
class WebformElements extends SalesforceMappingFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public static function isAllowed(SalesforceMappingInterface $mapping) {
    return $mapping->getDrupalEntityType() == 'webform_submission';
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $pluginForm = parent::buildConfigurationForm($form, $form_state);

    $options = $this->getConfigurationOptions($form['#entity']);

    if (empty($options)) {
      $pluginForm['drupal_field_value'] += [
        '#markup' => $this->t('No available webform elements.'),
      ];
    }
    else {
      $pluginForm['drupal_field_value'] += [
        '#type' => 'select',
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#default_value' => $this->config('drupal_field_value'),
        '#description' => $this->t('Select a webform element.'),
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
      $describe = $this
        ->salesforceClient
        ->objectDescribe($mapping->getSalesforceObjectType());
      $field_definition = $describe->getField($this->config('salesforce_field'));
      if ($field_definition['type'] == 'multipicklist') {
        return implode(';', $entity->getElementData($main_element_name));
      }
      else {
        $value = $entity->getElementData($main_element_name);
        if (isset($element_parts[1])) {
          $value = $value[$element_parts[1]];
        }
        return $value;
      }
    }
    catch (\Exception $e) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    $definition = parent::getPluginDefinition();
    $element_parts = explode('__', $this->config('drupal_field_value'));
    $main_element_name = reset($element_parts);
    $webform = $this->entityTypeManager->getStorage('webform')->load($this->mapping->get('drupal_bundle'));
    $definition['config_dependencies']['config'][] = $webform->getConfigDependencyName();
    $webform_element = $webform->getElement($main_element_name);
    // Unfortunately, the best we can do for webform dependencies is a single
    // dependency on the top-level webform, which is itself a monolithic config.
    // @TODO implement webform-element-changed hook, if that exists.
    $definition['config_dependencies']['config'][] = $webform->getConfigDependencyName();
  }

  /**
   * {@inheritdoc}
   */
  public function checkFieldMappingDependency(array $dependencies) {
    $definition = $this->getPluginDefinition();
    foreach ($definition['config_dependencies']['config'] as $dependency) {
      if (!empty($dependencies['config'][$dependency])) {
        return TRUE;
      }
    }
    return FALSE;
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

    // Loop over every field on the webform.
    foreach ($webform_elements as $element_id => $element) {
      if ($element['#type'] == 'webform_address') {
        $element = $webform->getElement($element_id, TRUE);
        foreach ($element['#webform_composite_elements'] as $sub_element) {
          $options[$sub_element['#webform_composite_key']] = $element['#title'] . ': ' . (string) $sub_element['#title'];
        }
      }
      else {
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
