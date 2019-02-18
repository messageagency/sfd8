<?php

namespace Drupal\salesforce_mapping\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AutocompleteController.
 */
class AutocompleteController extends ControllerBase {

  /**
   * Entity Field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * Constructs a new AutocompleteController object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   Entity field manager.
   */
  public function __construct(EntityFieldManagerInterface $field_manager) {
    $this->fieldManager = $field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager')
    );
  }

  /**
   * Autocomplete.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object providing the autocomplete query parameter.
   * @param string $entity_type_id
   *   The entity type filter options by.
   * @param string $bundle
   *   The bundle of the entity to filter options by.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The JSON results.
   */
  public function autocomplete(Request $request, $entity_type_id, $bundle) {
    $string = Html::escape(mb_strtolower($request->query->get('q')));
    $field_definitions = $this->fieldManager->getFieldDefinitions($entity_type_id, $bundle);

    // Filter out EntityReference Items.
    foreach ($field_definitions as $index => $field_definition) {
      if ($field_definition->getType() === 'entity_reference') {
        unset($field_definitions[$index]);
      }
    }
    $results = $this
      ->getDataFetcher()
      ->autocompletePropertyPath($field_definitions, $string);

    return new JsonResponse($results);
  }

  /**
   * Gets the data fetcher, or throw an exception if typed_data not installed.
   *
   * @return \Drupal\typed_data\DataFetcherInterface
   *   The data fetcher.
   *
   * @throws \Exception
   */
  protected function getDataFetcher() {
    if (!\Drupal::hasService('typed_data.data_fetcher')) {
      throw new \Exception('Module typed_data must be installed to use Extended Properties');
    }
    if (empty($this->dataFetcher)) {
      $this->dataFetcher = \Drupal::service('typed_data.data_fetcher');
    }
    return $this->dataFetcher;
  }

}
