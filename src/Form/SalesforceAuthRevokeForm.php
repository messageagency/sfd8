<?php

namespace Drupal\salesforce\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Entity\SalesforceAuthConfig;

/**
 * Class SalesforceAuthRevokeForm.
 */
class SalesforceAuthRevokeForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to revoke authorization for Auth Config %name?', ['%name' => $this->entity->label()]);
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
    return $this->t('Revoke Auth Token');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$this->entity instanceof SalesforceAuthConfig) {
      return;
    }
    $this->entity->getPlugin()->revokeAccessToken();

    // Set a message that the entity was deleted.
    $this->messenger()->addStatus($this->t('Auth token for %label was revoked.', [
      '%label' => $this->entity->label(),
    ]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
