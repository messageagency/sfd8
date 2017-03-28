<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\SalesforceMappingList.
 */

namespace Drupal\salesforce_mapping;

use Drupal\Core\Config\Entity\DraggableListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Defines the filter format list controller.
 */
class SalesforceMappingList extends DraggableListBuilder {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'salesforce_mapping_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [];
    $header['label'] = $this->t('Label');
    $header['drupal_entity_type'] = $this->t('Drupal Entity');
    $header['drupal_bundle'] = $this->t('Drupal Bundle');
    $header['salesforce_object_type'] = $this->t('Salesforce Object');
    // "status" means something new now.
    // @TODO rename old "Status" field
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = [];
    $row['label'] = $entity->label();
    $properties = ['drupal_entity_type', 'drupal_bundle', 'salesforce_object_type'];
    foreach ($properties as $property) {
      $row[$property] = $entity->get($property);
    }

    // If this mapping is disabled, denote it visually.
    if (!$entity->status()) {
      $row['status'] = ['#markup' => $this->t('Disabled')];
    }
    else {
      $row['status'] = ['#markup' => $this->t('Enabled')];
    }

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  // public function buildForm(array $form, FormStateInterface $form_state) {
  //   $form = parent::buildForm($form, $form_state);
  //   $form['actions']['submit']['#value'] = $this->t('Save changes');
  //   return $form;
  // }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);

    $url = Url::fromRoute('entity.salesforce_mapping.fields', ['salesforce_mapping' => $entity->id()]);

    // Only makes sense to expose fields operation if edit exists
    if (isset($operations['edit'])) {
      $operations['edit']['title'] = $this->t('Properties');
      $operations['fields'] = [
        'title' => $this->t('Fields'),
        'url' => $url,
        'weight' => -1000,
      ];
    }

    return $operations;
  }

}
