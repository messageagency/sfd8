<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Plugin\SalesforceMappingField\Constant.
 */

namespace Drupal\salesforce_mapping\Plugin\SalesforceMappingField;

use Drupal\Component\Annotation\Plugin;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Token;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;

/**
 * Adapter for entity Constant and fields.
 *
 * @Plugin(
 *   id = "Constant",
 *   label = @Translation("Constant")
 * )
 */
class Constant extends SalesforceMappingFieldPluginBase {

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $pluginForm = parent::buildConfigurationForm($form, $form_state);

    $pluginForm['drupal_field_value'] += [
      '#type' => 'textfield',
      '#default_value' => $this->config('drupal_field_value'),
      '#description' => $this->t('Enter a constant value to map to a Salesforce field.'),
    ];

    // @TODO: "Constant" as it's implemented now should only be allowed to be set to "Push". Remove other directionality options. In the future: create "Pull" logic for constant, which pulls a constant value to a Drupal field. Probably a separate mapping field plugin.
    
    return $pluginForm;

  }

  public function value(EntityInterface $entity) {
    return $this->config('drupal_field_value');
  }

  public function pull() {
    return FALSE;
  }

  // @TODO add validation handler (?)

}