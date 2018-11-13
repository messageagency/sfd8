<?php

namespace Drupal\salesforce_encrypt\Plugin\SalesforceAuthProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\salesforce\EntityNotFoundException;
use Drupal\salesforce\Plugin\SalesforceAuthProvider\SalesforceOAuthPlugin;
use Drupal\salesforce\Rest\RestResponse;
use Drupal\salesforce\Consumer\OAuthCredentials;
use Drupal\salesforce\Entity\SalesforceAuthConfig;
use Drupal\salesforce\SalesforceAuthProviderPluginBase;
use Drupal\salesforce\SalesforceOAuthPluginInterface;
use Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface;
use Drupal\salesforce\Token\SalesforceToken;
use Drupal\salesforce_encrypt\Consumer\OAuthEncryptedCredentials;
use Drupal\salesforce_encrypt\SalesforceEncryptedAuthTokenStorageInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Uri\Uri;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * @Plugin(
 *   id = "oauth_encrypted",
 *   label = @Translation("Salesforce OAuth User-Agent, Encrypted")
 * )
 */
class SalesforceEncryptedOAuthPlugin extends SalesforceAuthProviderPluginBase implements SalesforceOAuthPluginInterface {

  /** @var \Drupal\salesforce\Consumer\OAuthCredentials */
  protected $credentials;

  /**
   * @var \Drupal\encrypt\EncryptionProfileManagerInterface
   */
  protected $encryptionProfileManager;

  /**
   * @var \Drupal\encrypt\EncryptServiceInterface
   */
  protected $encryption;

  /**
   * @var \Drupal\encrypt\EncryptionProfileInterface
   */
  protected $encryptionProfile;

  /**
   * @var string
   */
  protected $encryptionProfileId;

  const SERVICE_TYPE = 'oauth_encrypted';
  const LABEL = 'OAuth Encrypted';

  /**
   * @var SalesforceEncryptedAuthTokenStorageInterface
   */
  protected $storage;

  public function __construct($id, OAuthEncryptedCredentials $credentials, ClientInterface $httpClient, SalesforceEncryptedAuthTokenStorageInterface $storage, EncryptionProfileManagerInterface $encryptionProfileManager, EncryptServiceInterface $encrypt) {
    parent::__construct($credentials, $httpClient, $storage, [], new Uri($credentials->getLoginUrl()));
    $this->id = $id;
    $this->encryptionProfileManager = $encryptionProfileManager;
    $this->encryption = $encrypt;
    $this->encryptionProfileId = $credentials->getEncryptionProfileId();
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $configuration = array_merge(self::defaultConfiguration(), $configuration);
    $storage = $container->get('salesforce.auth_token_storage');
    /** @var EncryptServiceInterface $encrypt */
    $encrypt = $container->get('encryption');
    $encryptProfileMan = $container->get('encrypt.encryption_profile.manager');
    if ($configuration['encryption_profile']) {
      try {
        $profile = $encryptProfileMan->getEncryptionProfile($configuration['encryption_profile']);
        $configuration['consumer_key'] = $encrypt->decrypt($configuration['consumer_key'], $profile);
        $configuration['consumer_secret'] = $encrypt->decrypt($configuration['consumer_secret'], $profile);
      }
      catch (\Exception $e) {
        // Any exception here may cause WSOD, don't allow that to happen.
        watchdog_exception('SFOAuthEncrypted', $e);
      }
    }
    $cred = new OAuthEncryptedCredentials($configuration['consumer_key'], $configuration['login_url'], $configuration['consumer_secret'], $configuration['encryption_profile']);
    return new static($configuration['id'], $cred, $container->get('salesforce.http_client_wrapper'), $storage, $encryptProfileMan, $encrypt);
  }

  public static function defaultConfiguration() {
    $defaults = parent::defaultConfiguration();
    return array_merge($defaults, [
      'encryption_profile' => NULL,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function hookEncryptionProfileDelete(EncryptionProfileInterface $profile) {
    if ($this->encryptionProfile()->id() == $profile->id()) {
      // @todo decrypt identity, access token, refresh token, consumer secret, consumer key and re-save
    }
  }

  public function encryptionProfile() {
    if ($this->encryptionProfile) {
      return $this->encryptionProfile;
    }
    elseif (empty($this->encryptionProfileId)) {
      return NULL;
    }
    else {
      $this->encryptionProfile = $this->encryptionProfileManager
        ->getEncryptionProfile($this->encryptionProfileId);
      if (empty($this->encryptionProfile)) {
        throw new EntityNotFoundException(['id' => $this->encryptionProfileId], 'encryption_profile');
      }
      return $this->encryptionProfile;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $options = $this
      ->encryptionProfileManager
      ->getEncryptionProfileNamesAsOptions();
    $default = NULL;
    try {
      $profile = $this->encryptionProfile();
      if (!empty($profile)) {
        $default = $profile->id();
      }
    }
    catch (EntityNotFoundException $e) {
      $this->messenger()->addError($e->getFormattableMessage());
      $this->messenger()->addError($this->t('Error while loading encryption profile. You will need to assign a new encryption profile and re-authenticate to Salesforce.'));
    }

    if (empty($options)) {
      $this->messenger()->addError($this->t('Please <a href="@href">create an encryption profile</a> before adding an OAuth Encrypted provider.', ['@href' => Url::fromRoute('entity.encryption_profile.add_form')->toString()]));
    }

    $form['consumer_key'] = [
      '#title' => t('Salesforce consumer key'),
      '#type' => 'textfield',
      '#description' => t('Consumer key of the Salesforce remote application you want to grant access to. VALUE WILL BE ENCRYPTED ON FORM SUBMISSION.'),
      '#required' => TRUE,
      '#default_value' => $this->credentials->getConsumerKey()
    ];

    $form['consumer_secret'] = [
      '#title' => $this->t('Salesforce consumer secret'),
      '#type' => 'textfield',
      '#description' => $this->t('Consumer secret of the Salesforce remote application. VALUE WILL BE ENCRYPTED ON FORM SUBMISSION.'),
      '#required' => TRUE,
      '#default_value' => $this->credentials->getConsumerSecret()
    ];

    $form['login_url'] = [
      '#title' => t('Login URL'),
      '#type' => 'textfield',
      '#default_value' => $this->credentials->getLoginUrl(),
      '#description' => t('Enter a login URL, either https://login.salesforce.com or https://test.salesforce.com.'),
      '#required' => TRUE,
    ];
    $form['encryption_profile'] = [
      '#type' => 'select',
      '#title' => $this->t('Encryption Profile'),
      '#description' => $this->t('Choose an encryption profile with which to encrypt Salesforce information.'),
      '#options' => $options,
      '#default_value' => $default,
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValues());
    $settings = $form_state->getValue('provider_settings');
    $this->encryptionProfileId = $settings['encryption_profile'];
    $consumer_key = $settings['consumer_key'];
    $settings['consumer_key'] = $this->encrypt($settings['consumer_key']);
    $settings['consumer_secret'] = $this->encrypt($settings['consumer_secret']);
    $form_state->setValue('provider_settings', $settings);
    parent::submitConfigurationForm($form, $form_state);

    // Write the config id to private temp store, so that we can use the same
    // callback URL for all OAuth applications in Salesforce.
    /** @var \Drupal\Core\TempStore\PrivateTempStore $tempstore */
    $tempstore = \Drupal::service('user.private_tempstore')->get('salesforce_oauth');
    $tempstore->set('config_id', $form_state->getValue('id'));

    try {
      $path = $this->getAuthorizationEndpoint();
      $query = [
        'redirect_uri' => self::getAuthCallbackUrl(),
        'response_type' => 'code',
        'client_id' => $consumer_key,
      ];

      // Send the user along to the Salesforce OAuth login form. If successful,
      // the user will be redirected to {redirect_uri} to complete the OAuth
      // handshake, and thence to the entity listing. Upon failure, the user
      // redirect URI will send the user back to the edit form.
      $response = new TrustedRedirectResponse($path . '?' . http_build_query($query), 302);
      $response->send();
      return;
    }
    catch (\Exception $e) {
      $this->messenger()->addError(t("Error during authorization: %message", ['%message' => $e->getMessage()]));
      // $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e));
    }
  }

  /**
   * @param string $value
   *
   * @return string
   * @throws \Drupal\encrypt\Exception\EncryptException
   */
  public function decrypt($value) {
    return $this->encryption->decrypt($value, $this->encryptionProfile());
  }

  /**
   * @param string $value
   *
   * @return string
   * @throws \Drupal\encrypt\Exception\EncryptException
   */
  public function encrypt($value) {
    return $this->encryption->encrypt($value, $this->encryptionProfile());
  }

  public static function getAuthCallbackUrl() {
    return Url::fromRoute('salesforce.oauth_callback', [], [
      'absolute' => TRUE,
      'https' => TRUE,
    ])->toString();
  }

  public function getConsumerSecret() {
    return $this->credentials->getConsumerSecret();
  }

  /**
   * @return bool
   * @throws \OAuth\Common\Http\Exception\TokenResponseException
   * @see \Drupal\salesforce\Controller\SalesforceOAuthController
   */
  public function finalizeOauth() {
    $this->requestAccessToken(\Drupal::request()->get('code'));
    $token = $this->getAccessToken();

    // Initialize identity.
    $headers = [
      'Authorization' => 'OAuth ' . $token->getAccessToken(),
      'Content-type' => 'application/json',
    ];
    $data = $token->getExtraParams();
    $response = $this->httpClient->retrieveResponse(new Uri($data['id']), [], $headers);
    $identity = $this->parseIdentityResponse($response);
    $this->storage->storeIdentity($this->service(), $identity);
    return TRUE;
  }

}