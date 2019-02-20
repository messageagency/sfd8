<?php

namespace Drupal\salesforce;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\OAuth2\Service\Salesforce;

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
   * Machine name identifier.
   *
   * @var string
   */
  protected $id;

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
  public function getPluginId() {
    return $this->getConfiguration('id');
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginDefinition() {
    return $this->getConfiguration();
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
  public function getCredentials() {
    return $this->credentials;
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
    $this->setConfiguration($form_state->getValues());
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Initialize identity.
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

    parent::save($form, $form_state);
    try {
      $this->setConfiguration($form_state->getValues());

      \Drupal::messenger()->addStatus(t('Successfully connected to Salesforce as user %name.', ['%name' => $this->getIdentity()['display_name']]));
    }
    catch (\Exception $e) {
      $form_state->setError($form, $this->t('Failed to connect to Salesforce: %message', ['%message' => $e->getMessage()]));
    }

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
  public function type() {
    return static::SERVICE_TYPE;
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return static::LABEL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationEndpoint() {
    return new Uri($this->credentials->getLoginUrl() . static::AUTH_ENDPOINT_PATH);
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessTokenEndpoint() {
    return new Uri($this->credentials->getLoginUrl() . static::AUTH_TOKEN_PATH);
  }

  /**
   * {@inheritdoc}
   */
  public function hasAccessToken() {
    return $this->storage->hasAccessToken($this->id());
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
