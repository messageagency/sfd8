<?php

namespace Drupal\salesforce_jwt\Plugin\SalesforceAuthProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\key\KeyRepositoryInterface;
use Drupal\salesforce\SalesforceAuthProviderPluginBase;
use OAuth\Common\Http\Uri\Uri;
use Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Token\TokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Firebase\JWT\JWT;

/**
 * JWT Oauth plugin.
 *
 * @Plugin(
 *   id = "jwt",
 *   label = @Translation("Salesforce JWT OAuth"),
 *   credentials_class = "\Drupal\salesforce_jwt\Consumer\JWTCredentials"
 * )
 */
class SalesforceJWTPlugin extends SalesforceAuthProviderPluginBase {

  /**
   * The credentials for this auth plugin.
   *
   * @var \Drupal\salesforce_jwt\Consumer\JWTCredentials
   */
  protected $credentials;

  /**
   * Key repository service.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * SalesforceAuthServiceBase constructor.
   *
   * @param array $configuration
   *   Configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \OAuth\Common\Http\Client\ClientInterface $httpClient
   *   Http client wrapper.
   * @param \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface $storage
   *   Token storage.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   Key repository.
   *
   * @throws \OAuth\OAuth2\Service\Exception\InvalidScopeException
   *   On error.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $httpClient, SalesforceAuthTokenStorageInterface $storage, KeyRepositoryInterface $keyRepository) {
    $this->keyRepository = $keyRepository;
    parent::__construct($configuration, $plugin_id, $plugin_definition, $httpClient, $storage);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $configuration = array_merge(self::defaultConfiguration(), $configuration);
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('salesforce.http_client_wrapper'), $container->get('salesforce.auth_token_storage'), $container->get('key.repository'));
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultConfiguration() {
    $defaults = parent::defaultConfiguration();
    return array_merge($defaults, [
      'login_user' => '',
      'encrypt_key' => '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getLoginUrl() {
    return $this->getCredentials()->getLoginUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    if (!$this->keyRepository->getKeyNamesAsOptions(['type' => 'authentication'])) {
      $this->messenger()->addError($this->t('Please <a href="@href">add an authentication key</a> before creating a JWT Auth provider.', ['@href' => Url::fromRoute('entity.key.add_form')->toString()]));
      return $form;
    }
    $form['consumer_key'] = [
      '#title' => $this->t('Salesforce consumer key'),
      '#type' => 'textfield',
      '#description' => $this->t('Consumer key of the Salesforce remote application you want to grant access to'),
      '#required' => TRUE,
      '#default_value' => $this->getCredentials()->getConsumerKey(),
    ];

    $form['login_user'] = [
      '#title' => $this->t('Salesforce login user'),
      '#type' => 'textfield',
      '#description' => $this->t('User account to issue token to'),
      '#required' => TRUE,
      '#default_value' => $this->getCredentials()->getLoginUser(),
    ];

    $form['login_url'] = [
      '#title' => $this->t('Login URL'),
      '#type' => 'textfield',
      '#default_value' => $this->getCredentials()->getLoginUrl(),
      '#description' => $this->t('Enter a login URL, either https://login.salesforce.com or https://test.salesforce.com.'),
      '#required' => TRUE,
    ];

    // Can't use key-select input type here because its #process method doesn't
    // fire on ajax, so the list is empty. DERP.
    $form['encrypt_key'] = [
      '#title' => 'Private Key',
      '#type' => 'select',
      '#empty_option' => $this->t('- Select -'),
      '#options' => $this->keyRepository->getKeyNamesAsOptions(['type' => 'authentication']),
      '#required' => TRUE,
      '#default_value' => $this->getCredentials()->getKeyId(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    if (empty($form_state->getValue('provider_settings')) && $form_state->getValue('provider_settings') == self::defaultConfiguration()) {
      $form_state->setError($form, $this->t('Please fill in JWT provider settings.'));
      return;
    }
    $this->setConfiguration($form_state->getValue('provider_settings'));
    // Force new credentials from form input, rather than storage.
    unset($this->credentials);
    try {
      // Bootstrap here by setting ID to provide a key to token storage.
      $this->id = $form_state->getValue('id');
      $this->requestAccessToken($this->generateAssertion());
    }
    catch (\Exception $e) {
      $form_state->setError($form, $e->getMessage());
    }
  }

  /**
   * Overrides AbstractService::requestAccessToken for jwt-bearer flow.
   *
   * @param string $assertion
   *   The JWT assertion.
   * @param string $state
   *   Not used.
   *
   * @return \OAuth\Common\Token\TokenInterface
   *   Access Token.
   *
   * @throws \OAuth\Common\Http\Exception\TokenResponseException
   */
  public function requestAccessToken($assertion, $state = NULL) {
    $data = [
      'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      'assertion' => $assertion,
    ];
    $response = $this->httpClient->retrieveResponse(new Uri($this->getLoginUrl() . static::AUTH_TOKEN_PATH), $data, ['Content-Type' => 'application/x-www-form-urlencoded']);
    $token = $this->parseAccessTokenResponse($response);
    $this->storage->storeAccessToken($this->service(), $token);
    return $token;
  }

  /**
   * {@inheritDoc}
   */
  public function refreshAccessToken(TokenInterface $token) {
    return $this->requestAccessToken($this->generateAssertion());
  }

  /**
   * Returns a JWT Assertion to authenticate.
   *
   * @return string
   *   JWT Assertion.
   */
  protected function generateAssertion() {
    $key = $this->keyRepository->getKey($this->getCredentials()->getKeyId())->getKeyValue();
    $token = $this->generateAssertionClaim();
    return JWT::encode($token, $key, 'RS256');
  }

  /**
   * Returns a JSON encoded JWT Claim.
   *
   * @return array
   *   The claim array.
   */
  protected function generateAssertionClaim() {
    $cred = $this->getCredentials();
    return [
      'iss' => $cred->getConsumerKey(),
      'sub' => $cred->getLoginUser(),
      'aud' => $cred->getLoginUrl(),
      'exp' => \Drupal::time()->getCurrentTime() + 60,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    $definition = parent::getPluginDefinition();
    if ($this->configuration['encrypt_key'] && $key = $this->keyRepository->getKey($this->configuration['encrypt_key'])) {
      $definition['config_dependencies']['config'][] = $key->getConfigDependencyName();
    }
    return $definition;
  }

}
