<?php

namespace Drupal\salesforce_example\Plugin\SalesforceMappingField;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_mapping\MappingConstants;

/**
 * Adapter for entity Constant and fields.
 *
 * @Plugin(
 *   id = "hardcoded",
 *   label = @Translation("Hardcoded Value")
 * )
 */
class Hardcoded extends SalesforceMappingFieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $pluginForm = parent::buildConfigurationForm($form, $form_state);

    $pluginForm['drupal_field_value'] += [
      '#type' => 'textfield',
      '#default_value' => 'Hardcoded value',
      '#description' => $this->t('This is a hardcoded value.'),
      '#disabled' => TRUE,
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
    return 'Hardcoded value';
  }

  /**
   * {@inheritdoc}
   */
  public function pull() {
    return FALSE;
  }

}
