<?php

namespace Drupal\salesforce_mapping\Plugin\SalesforceMappingField;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token as TokenService;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce\Rest\RestClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Adapter for entity Token and fields.
 *
 * @Plugin(
 *   id = "Token",
 *   label = @Translation("Token"),
 *   provider = "token"
 * )
 */
class Token extends SalesforceMappingFieldPluginBase {

  protected $token;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeBundleInfoInterface $entity_type_bundle_info, EntityFieldManagerInterface $entity_field_manager, RestClientInterface $rest_client, EntityManagerInterface $entity_manager, EntityTypeManagerInterface $etm, DateFormatterInterface $dateFormatter, EventDispatcherInterface $event_dispatcher, TokenService $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_bundle_info, $entity_field_manager, $rest_client, $entity_manager, $etm,  $dateFormatter,  $event_dispatcher);
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('entity_type.bundle.info'),
      $container->get('entity_field.manager'),
      $container->get('salesforce.client'),
      $container->get('entity.manager'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('event_dispatcher'),
      $container->get('token')
    );
  }

  /**
   *
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $pluginForm = parent::buildConfigurationForm($form, $form_state);

    // @TODO expose token options on mapping form: clear, callback, sanitize
    // @TODO expose token tree / selector
    // @TODO add token validation
    $pluginForm['drupal_field_value'] += [
      '#type' => 'textfield',
      '#default_value' => $this->config('drupal_field_value'),
      '#description' => $this->t('Enter a token to map a Salesforce field..'),
    ];

    // @TODO: "Constant" as it's implemented now should only be allowed to be set to "Push". In the future: create "Pull" logic for constant, which pulls a constant value to a Drupal field. Probably a separate mapping field plugin.
    $pluginForm['direction']['#options'] = [
      MappingConstants::SALESFORCE_MAPPING_DIRECTION_DRUPAL_SF => $pluginForm['direction']['#options'][MappingConstants::SALESFORCE_MAPPING_DIRECTION_DRUPAL_SF],
    ];
    $pluginForm['direction']['#default_value'] = MappingConstants::SALESFORCE_MAPPING_DIRECTION_DRUPAL_SF;

    return $pluginForm;
  }

  /**
   *
   */
  public function value(EntityInterface $entity, SalesforceMappingInterface $mapping) {
    // Even though everything is an entity, some token functions expect to
    // receive the entity keyed by entity type.
    $text = $this->config('drupal_field_value');
    $data = ['entity' => $entity, get_class($entity) => $entity];
    return $this->token->replace($text, $data);
  }

  /**
   *
   */
  public function pull() {
    return FALSE;
  }

}
