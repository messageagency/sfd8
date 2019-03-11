<?php

namespace Drupal\salesforce_mapping_ui\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local task definitions for all entity bundles.
 */
class SalesforceMappingLocalTask extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $etm;

  /**
   * Creates an SalesforceMappingLocalTask object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   The entity manager.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation manager.
   */
  public function __construct(EntityTypeManagerInterface $etm, TranslationInterface $string_translation) {
    $this->etm = $etm;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    foreach ($this->etm->getDefinitions() as $entity_type_id => $entity_type) {
      if (!($has_canonical_path = $entity_type->hasLinkTemplate('salesforce'))) {
        continue;
      }
      $this->derivatives["$entity_type_id.salesforce_tab"] = [
        'route_name' => "entity.$entity_type_id.salesforce",
        'title' => $this->t('Salesforce'),
        'base_route' => "entity.$entity_type_id.canonical",
        'weight' => 200,
      ] + $base_plugin_definition;
      $this->derivatives["$entity_type_id.salesforce"] = [
        'route_name' => "entity.$entity_type_id.salesforce",
        'weight' => 200,
        'title' => $this->t('View'),
        'parent_id' => "salesforce_mapping.entities:$entity_type_id.salesforce_tab",
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
