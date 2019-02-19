<?php

namespace Drupal\salesforce\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Class SalesforceAuthDeleteForm.
 */
class SalesforceAuthDeleteForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the Auth Config %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->entity->toUrl('collection');
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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    if ($form_state->getErrors()) {
      return;
    }
    if (\Drupal::config('salesforce.settings')->get('salesforce_auth_provider') == $this->entity->id()) {
      $form_state->setError($form, $this->t('You cannot delete the default auth provider. Please <a href="@href">assign a new auth provider</a> before deleting the active one.', ['@href' => Url::fromRoute('salesforce.auth_config')->toString()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();

    // Set a message that the entity was deleted.
    $this->messenger()->addStatus($this->t('Auth Config %label was deleted.', [
      '%label' => $this->entity->label(),
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
