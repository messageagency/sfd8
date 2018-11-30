<?php

namespace Drupal\salesforce_mapping\Plugin\SalesforceMappingField;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\MappingConstants;

/**
 * Adapter for entity Constant and fields.
 *
 * @Plugin(
 *   id = "Constant",
 *   label = @Translation("Constant")
 * )
 */
class Constant extends SalesforceMappingFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $pluginForm = parent::buildConfigurationForm($form, $form_state);

    $pluginForm['drupal_field_value'] += [
      '#type' => 'textfield',
      '#default_value' => $this->config('drupal_field_value'),
      '#description' => $this->t('Enter a constant value to map to a Salesforce field.'),
    ];

    // @TODO: "Constant" as it's implemented now should only be allowed to be set to "Push". In the future: create "Pull" logic for constant, which pulls a constant value to a Drupal field. Probably a separate mapping field plugin.
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
    return $this->config('drupal_field_value');
  }

  /**
   * {@inheritdoc}
   */
  public function pull() {
    return FALSE;
  }

}
