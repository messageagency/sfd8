<?php

namespace Drupal\salesforce_auth\Service;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\salesforce_auth\SalesforceAuthProviderInterface;
use Drupal\salesforce_auth\Consumer\JWTCredentials;
use Drupal\salesforce_auth\Storage\SalesforceAuthTokenStorageInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Storage\Exception\TokenNotFoundException;
use OAuth\OAuth2\Service\Salesforce;
use OAuth\Common\Http\Uri\Uri;

abstract class SalesforceAuthServiceBase extends Salesforce implements SalesforceAuthProviderInterface {

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
  public function getConfiguration() {
    return $this->configuration;
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
