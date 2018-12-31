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

  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

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
  public function getInstanceUrl() {
    return $this->getAccessToken()->getExtraParams()['instance_url'];
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
