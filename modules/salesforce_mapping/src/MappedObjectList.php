<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\MappedObjectList.
 */

namespace Drupal\salesforce_mapping;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\salesforce_mapping\MappedObject;

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
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
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
    $build['description'] = array(
      '#markup' => $this->t('Manage the fields on the <a href="@adminlink">Mappings</a>.', array(
        '@adminlink' => $this->urlGenerator->generateFromRoute('entity.salesforce_mapping.list'),
      )),
    );
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
    $header['id'] = $this->t('ID');
    $header['entity_id'] = $this->t('Entity ID');
    $header['entity_type'] = $this->t('Entity Type');
    $header['salesforce_id'] = $this->t('Salesforce ID');
    $header['changed'] = $this->t('Last Updated');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\salesforce_mapping\MappedObject */
    $row['id'] = $entity->id();
    $row['entity_id'] = $entity->entity_id->value;
    $row['entity_type'] = $entity->entity_type->value;
    $row['salesforce_id'] = $entity->sfid();
    $row['changed'] = $entity->get('changed')->value;
    return $row + parent::buildRow($entity);
  }

}
