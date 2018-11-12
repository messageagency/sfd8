<?php

namespace Drupal\salesforce;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use OAuth\OAuth2\Service\Salesforce;

abstract class SalesforceAuthProviderPluginBase extends Salesforce implements SalesforceAuthProviderInterface {

  use StringTranslationTrait;
  use DependencySerializationTrait;
  use MessengerTrait;

  /**
   * @var \Drupal\salesforce\Consumer\SalesforceCredentials
   */
  protected $credentials;

  /** @var array */
  protected $configuration;

  /** @var \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface */
  protected $storage;

  /** @var string */
  protected $id;

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

  public function id() {
    return $this->id;
  }

  public function type() {
    return static::SERVICE_TYPE;
  }

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
   * @param $responseBody
   *   JSON identity response from Salesforce.
   *
   * @return array
   * @throws \OAuth\Common\Http\Exception\TokenResponseException
   */
  protected function parseIdentityResponse($responseBody) {
    $data = json_decode($responseBody, true);

    if (null === $data || !is_array($data)) {
      throw new TokenResponseException('Unable to parse response.');
    }
    elseif (isset($data['error'])) {
      throw new TokenResponseException('Error in retrieving token: "' . $data['error'] . '"');
    }
    return $data;
  }

  /**
   * Accessor to the storage adapter to be able to retrieve tokens
   *
   * @return \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface
   */
  public function getStorage() {
    return $this->storage;
  }

}