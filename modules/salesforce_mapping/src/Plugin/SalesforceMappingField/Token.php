<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Plugin\SalesforceMappingField\Token.
 */

namespace Drupal\salesforce_mapping\Plugin\SalesforceMappingField;

use Drupal\Component\Annotation\Plugin;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token as TokenService;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adapter for entity Token and fields.
 *
 * @Plugin(
 *   id = "Token",
 *   label = @Translation("Token")
 * )
 */
class Token extends SalesforceMappingFieldPluginBase {
 
  protected $token;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityFieldManagerInterface $entity_field_manager, TokenService $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_bundle_info, $entity_field_manager);
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('entity_type.bundle.info'), $container->get('entity_field.manager'), $container->get('token'));
  }

  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // @TODO expose token options on mapping form: clear, callback, sanitize
    // @TODO expose token tree / selector
    // @TODO add token validation
    return array(
      '#type' => 'textfield',
      '#default_value' => $this->config('drupal_field_value'),
      '#description' => $this->t('Enter a token to map a Salesforce field..'),
    );
  }

  public function value(EntityInterface $entity) {
    // Even though everything is an entity, some token functions expect to
    // receive the entity keyed by entity type.
    $text = $this->config('drupal_field_value');
    $data = array('entity' => $entity, get_class($entity) => $entity);
    return $this->token->replace($text, $data);
  }

}
