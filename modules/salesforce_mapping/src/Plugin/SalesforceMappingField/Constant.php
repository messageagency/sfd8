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
    return array(
      '#type' => 'textfield',
      '#default_value' => $this->config('drupal_field_value'),
      '#description' => $this->t('Enter a constant value to map to a Salesforce field.'),
    );
  }

  public function value(EntityInterface $entity) {
    // @TODO token replace goes here:
    return $this->config('drupal_field_value');
  }

  // @TODO add validation handler. Prevent user from submitting anything that
  // isn't a token.

}