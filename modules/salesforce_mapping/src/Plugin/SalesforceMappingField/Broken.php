<?php

namespace Drupal\salesforce_mapping\Plugin\SalesforceMappingField;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;

/**
 * Adapter for entity properties and fields.
 *
 * @Plugin(
 *   id = "broken",
 *   label = @Translation("Broken")
 * )
 */
class Broken extends SalesforceMappingFieldPluginBase {

  /**
   * Implementation of PluginFormInterface::buildConfigurationForm.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Try to preserve existing, broken config, so that it works again when the
    // plugin gets restored:
    $pluginForm = parent::buildConfigurationForm($form, $form_state);

    foreach ($this->config() as $key => $value) {
      if (!empty($pluginForm[$key])) {
        $pluginForm[$key]['#type'] = 'hidden';
        $pluginForm[$key]['#value'] = $value;
      }
    }

    $pluginForm['drupal_field_type'] = [
      '#type' => 'hidden',
      '#value' => $this->config('drupal_field_type')
    ];

    return ['message' => [
      '#markup' => '<div class="error">'
        . $this->t('The field plugin %plugin is broken or missing.', ['%plugin' => $this->config('drupal_field_type')]) 
          . '</div>',
    ]];
  }

  /**
   *
   */
  public function value(EntityInterface $entity, SalesforceMappingInterface $mapping) {
    return;
  }

  /**
   * Do not allow this mapping plugin to be added via UI.
   */
  public static function isAllowed(SalesforceMappingInterface $mapping) {
    return FALSE;
  }

}
