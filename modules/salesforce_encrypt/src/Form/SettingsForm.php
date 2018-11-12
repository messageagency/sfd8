<?php

namespace Drupal\salesforce_encrypt\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\salesforce\EntityNotFoundException;
use Drupal\salesforce_encrypt\SalesforceEncryptedAuthTokenStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Base form for key add and edit forms.
 */
class SettingsForm extends FormBase {

  protected $encryptionProfileManager;

  /**
   * @var \Drupal\salesforce_encrypt\SalesforceEncryptedAuthTokenStorageInterface
   */
  protected $storage;

  /**
   * Constructs a new key form base.
   *
   * @param \Drupal\Core\Config\Entity\ConfigEntityStorageInterface $storage
   *   The key storage.
   */
  public function __construct(StateInterface $state, EncryptionProfileManagerInterface $encryptionProfileManager, SalesforceEncryptedAuthTokenStorageInterface $storage) {
    $this->encryptionProfileManager = $encryptionProfileManager;
    $this->state = $state;
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('encrypt.encryption_profile.manager'),
      $container->get('salesforce.auth_token_storage')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'salesforce_encrypt_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $options = $this
      ->encryptionProfileManager
      ->getEncryptionProfileNamesAsOptions();
    $default = NULL;
    try {
      $profile = $this->storage->getEncryptionProfile();
      if (!empty($profile)) {
        $default = $profile->id();
      }
    }
    catch (EntityNotFoundException $e) {
      $this->messenger()->addError($e->getFormattableMessage());
      $this->messenger()->addError($this->t('Error while loading encryption profile. You will need to <a href=":encrypt">assign a new encryption profile</a>, then <a href=":oauth">re-authenticate to Salesforce</a>.', [':encrypt' => Url::fromRoute('salesforce_encrypt.settings')->toString(), ':oauth' => Url::fromRoute('salesforce.admin_config_salesforce')->toString()]));
    }

    $form['profile'] = [
      '#type' => 'select',
      '#title' => $this->t('Encryption Profile'),
      '#description' => $this->t('Choose an encryption profile with which to encrypt Salesforce information.'),
      '#options' => $options,
      '#default_value' => $default,
      '#empty_option' => TRUE,
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    // By default, render the form using system-config-form.html.twig.
    $form['#theme'] = 'system_config_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $old_profile_id = $this->state->get('salesforce_encrypt.profile');
    $profile_id = $form_state->getValue('profile');

    if ($old_profile_id == $profile_id) {
      // No change to encryption profile. Do nothing.
      return;
    }

    $profile = $this
      ->encryptionProfileManager
      ->getEncryptionProfile($profile_id);
    if (empty($profile_id)) {
      // New profile id empty: disable encryption.
      $this->storage->disableEncryption();
    }
    elseif (empty($old_profile_id)) {
      // Old profile id empty: enable encryption anew.
      $this->storage->enableEncryption($profile);
    }
    else {
      // Changing encryption profiles: disable, then re-enable.
      $this->storage->disableEncryption();
      $this->storage->enableEncryption($profile);
    }
    $this->state->resetCache();
    $this->messenger()->addStatus($this->t('The configuration options have been saved.'));
  }

}
