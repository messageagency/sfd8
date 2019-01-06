<?php

namespace Drupal\salesforce_encrypt\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\State\StateInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\salesforce\EntityNotFoundException;
use Drupal\salesforce\Storage\SalesforceAuthTokenStorage;
use Drupal\salesforce_encrypt\SalesforceEncrypt;
use Drupal\salesforce_encrypt\SalesforceEncryptTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Base form for key add and edit forms.
 */
class SettingsForm extends FormBase {

  use MessengerTrait;

  /**
   * Profile manager.
   *
   * @var \Drupal\encrypt\EncryptionProfileManagerInterface
   */
  protected $encryptionProfileManager;

  /**
   * State service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Auth token storage service.
   *
   * @var \Drupal\salesforce\Storage\SalesforceAuthTokenStorage
   */
  protected $authTokenStorage;

  /**
   * Auth config storage.
   *
   * @var \Drupal\salesforce_encrypt\Storage\SalesforceEncryptedAuthStorage
   */
  protected $authConfigStorage;

  /**
   * Salesforce encrypt service.
   *
   * @var \Drupal\salesforce_encrypt\SalesforceEncrypt
   */
  protected $salesforceEncrypt;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   * @param \Drupal\encrypt\EncryptionProfileManagerInterface $encryptionProfileManager
   *   Encryption profile manager service.
   */
  public function __construct(StateInterface $state, EncryptionProfileManagerInterface $encryptionProfileManager, SalesforceAuthTokenStorage $authTokenStorage, EntityTypeManagerInterface $etm, SalesforceEncrypt $salesforceEncrypt) {
    $this->encryptionProfileManager = $encryptionProfileManager;
    $this->state = $state;
    $this->authTokenStorage = $authTokenStorage;
    $this->authConfigStoage = $etm->getStorage('salesforce_auth');
    $this->salesforceEncrypt = $salesforceEncrypt;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('encrypt.encryption_profile.manager'),
      $container->get('salesforce.auth_token_storage'),
      $container->get('entity_type.manager'),
      $container->get('salesforce_encrypt.service')
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
      /** @var \Drupal\encrypt\EncryptionProfileInterface $profile */
      $profile = $this->salesforceEncrypt->getProfile();
      if (!empty($profile)) {
        $default = $profile->id();
      }
    }
    catch (EntityNotFoundException $e) {
      $this->messenger()->addError($e->getFormattableMessage());
      $this->messenger()->addError($this->t('Error while loading encryption profile. You will need to <a href=":encrypt">assign a new encryption profile</a>, then <a href=":oauth">re-authenticate to Salesforce</a>.', [':encrypt' => Url::fromRoute('salesforce_encrypt.settings')->toString(), ':oauth' => Url::fromRoute('salesforce.authorize')->toString()]));
    }

    $form['profile'] = [
      '#type' => 'select',
      '#title' => $this->t('Encryption Profile'),
      '#description' => $this->t('Choose an encryption profile with which to encrypt Salesforce information.'),
      '#options' => $options,
      '#default_value' => $default,
      '#empty_option' => $this->t('Do not use encryption'),
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // @TODO drop support for changing the profile if we have existing encrypted configs.
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
      $this->client->disableEncryption();
    }
    elseif (empty($old_profile_id)) {
      // Old profile id empty: enable encryption anew.
      $this->client->enableEncryption($profile);
    }
    else {
      // Changing encryption profiles: disable, then re-enable.
      $this->client->disableEncryption();
      $this->client->enableEncryption($profile);
    }
    $this->state->resetCache();
    drupal_set_message($this->t('The configuration options have been saved.'));
  }

}
