<?php

namespace Drupal\salesforce_auth\Plugin\SalesforceAuthProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_auth\Consumer\JWTCredentials;
use Drupal\salesforce_auth\SalesforceAuthProviderPluginBase;
use OAuth\OAuth2\Token\StdOAuth2Token;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use Drupal\salesforce_auth\Storage\SalesforceAuthTokenStorageInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Token\TokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Plugin(
 *   id = "jwt",
 *   label = @Translation("Salesforce JWT OAuth")
 * )
 */
class SalesforceJWTPlugin extends SalesforceAuthProviderPluginBase {

  /** @var \Drupal\salesforce_auth\Consumer\JWTCredentials */
  protected $credentials;

  const SERVICE_TYPE = 'jwt';
  const LABEL = 'JWT';

  /**
   * SalesforceAuthServiceBase constructor.
   *
   * @param string $id
   * @param \Drupal\salesforce_auth\Consumer\JWTCredentials $credentials
   * @param \OAuth\Common\Http\Client\ClientInterface $httpClient
   * @param \Drupal\salesforce_auth\Storage\SalesforceAuthTokenStorageInterface $storage
   *
   * @throws \OAuth\OAuth2\Service\Exception\InvalidScopeException
   */
  public function __construct($id, JWTCredentials $credentials, ClientInterface $httpClient, SalesforceAuthTokenStorageInterface $storage) {
    parent::__construct($credentials, $httpClient, $storage, [], new Uri($credentials->getLoginUrl()));
    $this->id = $id;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $configuration = array_merge(self::defaultConfiguration(), $configuration);
    $cred = new JWTCredentials($configuration['consumer_key'], $configuration['login_url'], $configuration['login_user'], $configuration['encrypt_key']);
    return new static($configuration['id'], $cred, $container->get('salesforce_auth.http_client_wrapper'), $container->get('salesforce_auth.token_storage'));
  }

  public static function defaultConfiguration() {
    $defaults = parent::defaultConfiguration();
    return array_merge($defaults, [
      'login_user' => '',
      'encrypt_key' => '',
    ]);
  }

  public function getLoginUrl() {
    return $this->credentials->getLoginUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
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

    // Can't use key-select here because its #process method is not firing on ajax, and the list is empty. DERP.
    $form['encrypt_key'] = [
      '#title' => 'Private Key',
      '#type' => 'select',
      '#options' => \Drupal::service('key.repository')->getKeyNamesAsOptions(['type' => 'authentication']),
      '#required' => TRUE,
      '#default_value' => $this->credentials->getEncryptKeyId(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    try {
      $this->requestAccessToken('');
      \Drupal::messenger()->addStatus(t('Successfully connected to Salesforce as user %name.', ['%name' => $this->getIdentity()['display_name']]));
    }
    catch (\Exception $e) {
      $form_state->setError($form, $this->t('Failed to connect to Salesforce: %message', ['%message' => $e->getMessage()]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function requestAccessToken($code, $state = NULL) {
    // Initialize access token.
    $assertion = $this->generateAssertion();
    $data = [
      'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      'assertion' => $assertion,
    ];
    $response = $this->httpClient->retrieveResponse(new Uri($this->getAuthTokenUrl()), $data, ['Content-Type' => 'application/x-www-form-urlencoded']);
    $token = $this->parseAccessTokenResponse($response);
    $this->storage->storeAccessToken($this->service(), $token);

    // Initialize identity.
    $headers = [
      'Authorization' => 'OAuth ' . $token->getAccessToken(),
      'Content-type' => 'application/json',
    ];
    $data = $token->getExtraParams();
    $response = $this->httpClient->retrieveResponse(new Uri($data['id']), [], $headers);
    $identity = $this->parseIdentityResponse($response);
    $this->storage->storeIdentity($this->service(), $identity);

    return $token;
  }

  /**
   * Refreshes an OAuth2 access token.
   *
   * @param TokenInterface $token
   *
   * @return TokenInterface $token
   *
   * @throws \OAuth\OAuth2\Service\Exception\MissingRefreshTokenException
   * @throws \OAuth\Common\Http\Exception\TokenResponseException
   */
  public function refreshAccessToken(TokenInterface $token) {
    $token = $this->requestAccessToken('');
    return $token;
  }

  /**
   * Returns a JWT Assertion to authenticate.
   *
   * @return string
   *   JWT Assertion.
   */
  private function generateAssertion() {
    $header = $this->generateAssertionHeader();
    $claim = $this->generateAssertionClaim();
    $header_encoded = $this->b64UrlEncode($header);
    $claim_encoded = $this->b64UrlEncode($claim);
    $encoded_string = $header_encoded . '.' . $claim_encoded;
    $key = \Drupal::service('key.repository')->getKey($this->credentials->getEncryptKeyId())->getKeyValue();
    openssl_sign($encoded_string, $signed, $key, 'sha256WithRSAEncryption');
    $signed_encoded = $this->b64UrlEncode($signed);
    $assertion = $encoded_string . '.' . $signed_encoded;
    return $assertion;
  }

  /**
   * Returns a JSON encoded JWT Header.
   *
   * @return string
   *   The encoded header.
   */
  private function generateAssertionHeader() {
    $header = new \stdClass();
    $header->alg = 'RS256';
    return json_encode($header);
  }

  /**
   * Returns a JSON encoded JWT Claim.
   *
   * @return string
   *   The encoded claim.
   */
  private function generateAssertionClaim() {
    $claim = new \stdClass();
    $claim->iss = $this->credentials->getConsumerKey();
    $claim->sub = $this->credentials->getLoginUser();
    $claim->aud = $this->credentials->getLoginUrl();
    $claim->exp = \Drupal::time()->getCurrentTime() + 60;
    return json_encode($claim);
  }

  /**
   * Base 64 URL Safe Encoding.
   *
   * @param string $data
   *   String to encode.
   *
   * @return string
   *   Encoded string.
   */
  private function b64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }


}