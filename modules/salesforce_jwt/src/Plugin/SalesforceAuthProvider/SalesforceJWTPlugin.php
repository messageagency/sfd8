<?php

namespace Drupal\salesforce_jwt\Plugin\SalesforceAuthProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\key\KeyRepositoryInterface;
use Drupal\salesforce_jwt\Consumer\JWTCredentials;
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
 *   label = @Translation("Salesforce JWT OAuth")
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
   * {@inheritdoc}
   */
  const SERVICE_TYPE = 'jwt';

  /**
   * {@inheritdoc}
   */
  const LABEL = 'JWT';

  /**
   * SalesforceAuthServiceBase constructor.
   *
   * @param string $id
   *   The plugin / auth config id.
   * @param \Drupal\salesforce_jwt\Consumer\JWTCredentials $credentials
   *   The credentials.
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
  public function __construct($id, JWTCredentials $credentials, ClientInterface $httpClient, SalesforceAuthTokenStorageInterface $storage, KeyRepositoryInterface $keyRepository) {
    parent::__construct($credentials, $httpClient, $storage, [], new Uri($credentials->getLoginUrl()));
    $this->id = $id;
    $this->keyRepository = $keyRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $configuration = array_merge(self::defaultConfiguration(), $configuration);
    $cred = new JWTCredentials($configuration['consumer_key'], $configuration['login_url'], $configuration['login_user'], $configuration['encrypt_key']);
    return new static($configuration['id'], $cred, $container->get('salesforce.http_client_wrapper'), $container->get('salesforce.auth_token_storage'), $container->get('key.repository'));
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
    return $this->credentials->getLoginUrl();
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
      '#title' => t('Salesforce consumer key'),
      '#type' => 'textfield',
      '#description' => t('Consumer key of the Salesforce remote application you want to grant access to'),
      '#required' => TRUE,
      '#default_value' => $this->credentials->getConsumerKey(),
    ];

    $form['login_user'] = [
      '#title' => $this->t('Salesforce login user'),
      '#type' => 'textfield',
      '#description' => $this->t('User account to issue token to'),
      '#required' => TRUE,
      '#default_value' => $this->credentials->getLoginUser(),
    ];

    $form['login_url'] = [
      '#title' => t('Login URL'),
      '#type' => 'textfield',
      '#default_value' => $this->credentials->getLoginUrl(),
      '#description' => t('Enter a login URL, either https://login.salesforce.com or https://test.salesforce.com.'),
      '#required' => TRUE,
    ];

    // Can't use key-select input type here because its #process method doesn't
    // fire on ajax, so the list is empty. DERP.
    $form['encrypt_key'] = [
      '#title' => 'Private Key',
      '#type' => 'select',
      '#options' => $this->keyRepository->getKeyNamesAsOptions(['type' => 'authentication']),
      '#required' => TRUE,
      '#default_value' => $this->credentials->getKeyId(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $this->setConfiguration($form_state->getValues());
    try {
      $values = $form_state->getValues();
      $this->id = $values['id'];
      $settings = $values['provider_settings'];
      $this->credentials = new JWTCredentials($settings['consumer_key'], $settings['login_url'], $settings['login_user'], $settings['encrypt_key']);
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
   * Refreshes a the JWT access token: pass-through to requestAccessToken().
   *
   * @param \OAuth\Common\Token\TokenInterface $token
   *   The JWT OAuth token to refresh.
   *
   * @return \OAuth\Common\Token\TokenInterface
   *   On success.
   *
   * @throws \OAuth\OAuth2\Service\Exception\MissingRefreshTokenException
   *   On error.
   * @throws \OAuth\Common\Http\Exception\TokenResponseException
   *   On error.
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
    $key = $this->keyRepository->getKey($this->credentials->getKeyId())->getKeyValue();
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
    return [
      'iss' => $this->credentials->getConsumerKey(),
      'sub' => $this->credentials->getLoginUser(),
      'aud' => $this->credentials->getLoginUrl(),
      'exp' => \Drupal::time()->getCurrentTime() + 60,
    ];
  }

}
