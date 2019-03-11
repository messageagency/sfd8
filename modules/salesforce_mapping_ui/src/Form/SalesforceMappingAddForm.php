<?php

namespace Drupal\salesforce_mapping_ui\Form;

use Drupal\Core\Form\FormStateInterface;

/**
 * Salesforce Mapping Add/Edit Form.
 */
class SalesforceMappingAddForm extends SalesforceMappingFormCrudBase {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $form_state->setRedirect('entity.salesforce_mapping.fields', ['salesforce_mapping' => $this->entity->id()]);
  }

}
