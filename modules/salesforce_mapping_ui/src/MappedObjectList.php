<?php

namespace Drupal\salesforce_mapping_ui;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a list controller for salesforce_mapping entity.
 *
 * @ingroup salesforce_mapping
 */
class MappedObjectList extends EntityListBuilder {

  /**
   * The url generator.
   *
   * @var \Drupal\Core\Routing\UrlGeneratorInterface
   */
  protected $urlGenerator;

  /**
   * Set entityIds to show a partial listing of mapped objects.
   *
   * @var array
   */
  protected $entityIds;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('url_generator')
    );
  }

  /**
   * Constructs a new MappedObjectList object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The url generator.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, UrlGeneratorInterface $url_generator) {
    parent::__construct($entity_type, $storage);
    $this->urlGenerator = $url_generator;
  }

  /**
   * {@inheritdoc}
   *
   * We override ::render() so that we can add our own content above the table.
   * parent::render() is where EntityListBuilder creates the table using our
   * buildHeader() and buildRow() implementations.
   */
  public function render() {
    $build['description'] = [
      '#markup' => $this->t('Manage the fields on the <a href="@adminlink">Mappings</a>.', [
        '@adminlink' => $this->urlGenerator->generateFromRoute('entity.salesforce_mapping.list'),
      ]),
    ];
    $build['table'] = parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the SF Mapped Object list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader() {
    $header['id'] = [
      'data' => $this->t('ID'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    $header['mapped_entity'] = $this->t('Entity');
    $header['salesforce_link'] = $this->t('Salesforce Record');
    $header['mapping'] = [
      'data' => $this->t('Mapping'),
      'class' => [RESPONSIVE_PRIORITY_MEDIUM],
    ];
    $header['changed'] = [
      'data' => $this->t('Last Updated'),
      'class' => [RESPONSIVE_PRIORITY_LOW],
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row['id'] = $entity->id();
    $row['mapped_entity']['data'] = $entity->drupal_entity->first()->view();
    $row['salesforce_link']['data'] = $entity->salesforce_link->first()->view();
    $row['mapping']['data'] = $entity->salesforce_mapping->first()->view();
    $row['changed'] = \Drupal::service('date.formatter')->format($entity->changed->value);
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(EntityInterface $entity) {
    $operations['view'] = [
      'title' => $this->t('View'),
      'weight' => -100,
      'url' => $entity->toUrl(),
    ];
    $operations += parent::getDefaultOperations($entity);
    return $operations;
  }

  /**
   * Set the given entity ids to show only those in a listing of mapped objects.
   *
   * @param array $ids
   *   The entity ids.
   *
   * @return $this
   */
  public function setEntityIds(array $ids) {
    $this->entityIds = $ids;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    // If we're building a partial list, only query for those entities.
    if (!empty($this->entityIds)) {
      return $this->entityIds;
    }
    return parent::getEntityIds();
  }

}
