<?php

namespace Drupal\salesforce_encrypt\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\salesforce\Entity\SalesforceAuthConfig;
use Drupal\salesforce_encrypt\SalesforceEncryptTrait;

/**
 * Auth encryption form.
 */
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
    return $this->t('Encrypt / decrypt auth config.');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$this->entity instanceof SalesforceAuthConfig) {
      return;
    }
    \Drupal::service('salesforce_encrypt.service')->encryptAuthConfig($this->entity);
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
