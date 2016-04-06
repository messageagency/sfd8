<?php

/**
 * @file
 * Contains Drupal\salesforce_mapping\SalesforceMappingDeleteForm.
 */

namespace Drupal\salesforce_mapping\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
  
/**
 * Salesforce Mapping Delete Form .
 */
class SalesforceMappingDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the mapping %name?', array('%name' => $this->entity->label()));
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.salesforce_mapping.list');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();

    // Set a message that the entity was deleted.
    drupal_set_message($this->t('Salesforce %label was deleted.', array(
      '%label' => $this->entity->label(),
    )));
    
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
