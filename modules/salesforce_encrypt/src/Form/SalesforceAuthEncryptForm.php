<?php

namespace Drupal\salesforce_encrypt\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\salesforce\Entity\SalesforceAuthConfig;
use Drupal\salesforce_encrypt\SalesforceEncryptTrait;

class SalesforceAuthEncryptForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Encrypt / decrypt Auth Config %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Encryption is currently %status for Auth Config %name.');
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