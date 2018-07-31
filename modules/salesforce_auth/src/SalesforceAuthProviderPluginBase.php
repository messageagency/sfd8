<?php

namespace Drupal\salesforce_auth;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\salesforce_auth\Service\SalesforceAuthServiceBase;
use OAuth\Common\Http\Uri\Uri;
use OAuth\OAuth2\Service\Salesforce;

abstract class SalesforceAuthProviderPluginBase extends Salesforce implements SalesforceAuthProviderInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * @var \Drupal\salesforce_auth\Consumer\SalesforceCredentials
   */
  protected $credentials;

  /** @var array */
  protected $configuration;

  /** @var \Drupal\salesforce_auth\Storage\SalesforceAuthTokenStorageInterface */
  protected $storage;

  /** @var string */
  protected $id;

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
    return $this->configuration['login_url'];
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
    // TODO: Implement validateConfigurationForm() method.
  }

  public function id() {
    return $this->id;
  }

  public function type() {
    return self::SERVICE_TYPE;
  }

  public function label() {
    return self::LABEL;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationEndpoint() {
    return new Uri($this->credentials->getLoginUrl() . '/services/oauth2/authorize');
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessTokenEndpoint() {
    return new Uri($this->credentials->getLoginUrl() . '/services/oauth2/token');
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken() {
    return $this->storage->retrieveAccessToken($this->service());
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentity() {
    return $this->storage->retrieveIdentity($this->service());
  }

  /**
   * {@inheritdoc}
   */
  public function service() {
    return $this->id();
  }

  /**
   * {@inheritdoc}
   */
  public function hasAccessToken() {
    return $this->storage->hasAccessToken($this->service());
  }


}