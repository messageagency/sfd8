<?php

namespace Drupal\salesforce_jwt\Plugin\SalesforceAuthProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\salesforce_jwt\Consumer\JWTCredentials;
use Drupal\salesforce\SalesforceAuthProviderPluginBase;
use OAuth\OAuth2\Token\StdOAuth2Token;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface;
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

  /** @var \Drupal\salesforce_jwt\Consumer\JWTCredentials */
  protected $credentials;

  const SERVICE_TYPE = 'jwt';
  const LABEL = 'JWT';

  /**
   * SalesforceAuthServiceBase constructor.
   *
   * @param string $id
   * @param \Drupal\salesforce_jwt\Consumer\JWTCredentials $credentials
   * @param \OAuth\Common\Http\Client\ClientInterface $httpClient
   * @param \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface $storage
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
    return new static($configuration['id'], $cred, $container->get('salesforce.http_client_wrapper'), $container->get('salesforce.auth_token_storage'));
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
    if (!$this->keyRepository()) {
      $this->messenger()->addError($this->t('JWT Auth requires <a href="https://drupal.org/project/key">Key</a> module. Please install before adding a JWT Auth config.'));
      return $form;
    }
    if (!$this->keyRepository()->getKeyNamesAsOptions(['type' => 'authentication'])) {
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

    // Can't use key-select here because its #process method is not firing on ajax, and the list is empty. DERP.
    $form['encrypt_key'] = [
      '#title' => 'Private Key',
      '#type' => 'select',
      '#options' => $this->keyRepository()->getKeyNamesAsOptions(['type' => 'authentication']),
      '#required' => TRUE,
      '#default_value' => $this->credentials->getEncryptKeyId(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$this->keyRepository()) {
      $form_state->setError($form, $this->t('JWT Auth requires <a href="https://drupal.org/project/key">Key</a> module. Please install before adding a JWT Auth config.'));
      return;
    }

    parent::validateConfigurationForm($form, $form_state);
    try {
      $settings = $form_state->getValue('provider_settings');
      $this->getToken($settings['login_url']);
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
   * Gets a token from the given JWT OAuth endpoint.
   */
  protected function getToken($login_url) {
    // Initialize access token.
    $assertion = $this->generateAssertion();
    $data = [
      'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      'assertion' => $assertion,
    ];
    $response = $this->httpClient->retrieveResponse(new Uri($login_url . static::AUTH_TOKEN_PATH), $data, ['Content-Type' => 'application/x-www-form-urlencoded']);
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
    $token = $this->getToken($this->getLoginUrl());
    return $token;
  }

  /**
   * Key repository wrapper.
   *
   * @return \Drupal\key\KeyRepository|FALSE
   *   The key repo.
   */
  protected function keyRepository() {
    if (!\Drupal::hasService('key.repository')) {
      return FALSE;
    }
    return \Drupal::service('key.repository');
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
    $key = $this->keyRepository()->getKey($this->credentials->getEncryptKeyId())->getKeyValue();
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