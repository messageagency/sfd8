<?php

namespace Drupal\salesforce;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\OAuth2\Service\Salesforce;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shared methods for auth providers.
 */
abstract class SalesforceAuthProviderPluginBase extends Salesforce implements SalesforceAuthProviderInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;
  use MessengerTrait;

  /**
   * Credentials.
   *
   * @var \Drupal\salesforce\Consumer\SalesforceCredentials
   */
  protected $credentials;

  /**
   * Configuration.
   *
   * @var array
   */
  protected $configuration;

  /**
   * Token storage.
   *
   * @var \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface
   */
  protected $storage;

  /**
   * Provider id, e.g. jwt, oauth.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * Plugin definition.
   *
   * @var array
   */
  protected $pluginDefinition;

  /**
   * Instance id, e.g. "sandbox1" or "production".
   *
   * @var string
   */
  protected $id;

  /**
   * SalesforceOAuthPlugin constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Plugin id.
   * @param mixed $plugin_definition
   *   Plugin definition.
   * @param \OAuth\Common\Http\Client\ClientInterface $httpClient
   *   The oauth http client.
   * @param \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface $storage
   *   Auth token storage service.
   *
   * @throws \OAuth\OAuth2\Service\Exception\InvalidScopeException
   *   Comment.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientInterface $httpClient, SalesforceAuthTokenStorageInterface $storage) {
    $this->id = !empty($configuration['id']) ? $configuration['id'] : NULL;
    $this->configuration = $configuration;
    $this->pluginDefinition = $plugin_definition;
    $this->pluginId = $plugin_id;
    $this->credentials = $this->getCredentials();
    parent::__construct($this->getCredentials(), $httpClient, $storage, [], new Uri($this->getCredentials()->getLoginUrl()));
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $configuration = array_merge(static::defaultConfiguration(), $configuration);
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('salesforce.http_client_wrapper'), $container->get('salesforce.auth_token_storage'));
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultConfiguration() {
    return [
      'consumer_key' => '',
      'login_url' => 'https://test.salesforce.com',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getPluginDefinition()['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->id;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginId() {
    return $this->pluginId;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    return $this->pluginDefinition;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration($key = NULL) {
    if ($key !== NULL) {
      return !empty($this->configuration[$key]) ? $this->configuration[$key] : NULL;
    }
    return $this->configuration;
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
  public function getConsumerKey() {
    return $this->credentials->getConsumerKey();
  }

  /**
   * {@inheritdoc}
   */
  public function getConsumerSecret() {
    return $this->credentials->getConsumerSecret();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValue('provider_settings'));
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Initialize identity if token is available.
    if (!$this->hasAccessToken()) {
      return TRUE;
    }
    $token = $this->getAccessToken();
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

  /**
   * {@inheritdoc}
   */
  public function getCredentials() {
    if (!$this->credentials || !$this->credentials->isValid()) {
      $pluginDefinition = $this->getPluginDefinition();
      $this->credentials = $pluginDefinition['credentials_class']::create($this->configuration);
    }
    return $this->credentials;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationEndpoint() {
    return new Uri($this->getCredentials()->getLoginUrl() . static::AUTH_ENDPOINT_PATH);
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessTokenEndpoint() {
    return new Uri($this->getCredentials()->getLoginUrl() . static::AUTH_TOKEN_PATH);
  }

  /**
   * {@inheritdoc}
   */
  public function hasAccessToken() {
    return $this->storage ? $this->storage->hasAccessToken($this->id()) : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken() {
    return $this->storage->retrieveAccessToken($this->id());
  }

  /**
   * {@inheritdoc}
   */
  public function revokeAccessToken() {
    return $this->storage->clearToken($this->id());
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceUrl() {
    return $this->getAccessToken()->getExtraParams()['instance_url'];
  }

  /**
   * {@inheritdoc}
   */
  public function getApiEndpoint($api_type = 'rest') {
    $url = &drupal_static(self::CLASS . __FUNCTION__ . $api_type);
    if (!isset($url)) {
      $identity = $this->getIdentity();
      if (empty($identity)) {
        return FALSE;
      }
      if (is_string($identity)) {
        $url = $identity;
      }
      elseif (isset($identity['urls'][$api_type])) {
        $url = $identity['urls'][$api_type];
      }
      $url = str_replace('{version}', $this->getApiVersion(), $url);
    }
    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiVersion() {
    $version = \Drupal::config('salesforce.settings')->get('rest_api_version.version');
    if (empty($version) || \Drupal::config('salesforce.settings')->get('use_latest')) {
      return self::LATEST_API_VERSION;
    }
    return \Drupal::config('salesforce.settings')->get('rest_api_version.version');
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentity() {
    return $this->storage->retrieveIdentity($this->id());
  }

  /**
   * {@inheritdoc}
   */
  public function service() {
    return $this->id();
  }

  /**
   * Handle the identity response from Salesforce.
   *
   * @param string $responseBody
   *   JSON identity response from Salesforce.
   *
   * @return array
   *   The identity.
   *
   * @throws \OAuth\Common\Http\Exception\TokenResponseException
   */
  protected function parseIdentityResponse($responseBody) {
    $data = json_decode($responseBody, TRUE);

    if (NULL === $data || !is_array($data)) {
      throw new TokenResponseException('Unable to parse response.');
    }
    elseif (isset($data['error'])) {
      throw new TokenResponseException('Error in retrieving token: "' . $data['error'] . '"');
    }
    return $data;
  }

  /**
   * Accessor to the storage adapter to be able to retrieve tokens.
   *
   * @return \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface
   *   The token storage.
   */
  public function getStorage() {
    return $this->storage;
  }

}
