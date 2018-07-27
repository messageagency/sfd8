<?php

namespace Drupal\salesforce_jwt\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_auth\Form\AuthConfigFormBase;
use Drupal\salesforce_jwt\Entity\JWTAuthConfig;

/**
 * Entity form for JWT Auth Config.
 */
class AuthConfigForm extends AuthConfigFormBase {

  /**
   * The config entity.
   *
   * @var \Drupal\salesforce_jwt\Entity\JWTAuthConfig
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->entity;
    $form['creds']['login_user'] = [
      '#title' => $this->t('Salesforce login user'),
      '#type' => 'textfield',
      '#description' => $this->t('User account to issue token to'),
      '#required' => TRUE,
      '#default_value' => $config->getLoginUser(),
    ];

    $form['creds']['encrypt_key'] = [
      '#title' => 'Private Key',
      '#type' => 'key_select',
      '#required' => TRUE,
      '#default_value' => $config->getEncryptKey(),
    ];

    return $form;
  }

}
