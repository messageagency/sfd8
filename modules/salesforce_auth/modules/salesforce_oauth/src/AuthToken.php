<?php

namespace Drupal\salesforce_oauth;

use Drupal\salesforce_auth\SalesforceAuthProviderInterface;
use Drupal\salesforce_auth\AuthTokenInterface;
use Drupal\salesforce_oauth\Entity\OAuthConfig;

/**
 * Represents an auth token (with lookups to correlated project config).
 */
class AuthToken implements AuthTokenInterface {

  protected $access_token;
  protected $refresh_token;
  protected $authConfigId;
  protected $authConfigLabel;
  protected $scope;
  protected $instance_url;
  protected $token_type;
  protected $issued_at;
  protected $id;
  protected $id_token;
  protected $signature;
  protected $identity;

  /**
   * The config for this project.
   *
   * @var \Drupal\salesforce_oauth\Entity\OAuthConfig
   */
  protected $config;

  /**
   * Create a new AuthToken stub from the given project.
   */
  public function __construct(array $values) {
    foreach ($values as $value) {
      $this->$value = $value;
    }
    if (!empty($this->authConfigId)) {
      $this->config = OAuthConfig::load($this->authConfigId);
    }
  }

  /**
   * Get the project label from correlated config.
   */
  public function label() {
    return $this->config->label();
  }

  /**
   * Access token setter.
   */
  public function setAccessToken($token) {
    $this->access_token = $token;
    return $this;
  }

  /**
   * Access token getter.
   */
  public function getAccessToken() {
    return $this->access_token;
  }

  /**
   * Refresh token setter.
   */
  public function setRefreshToken($token) {
    $this->refresh_token = $token;
    return $this;
  }

  /**
   * Refresh token getter.
   */
  public function getRefreshToken() {
    return $this->refresh_token;
  }

  /**
   * Project id getter.
   */
  public function getAuthConfigId() {
    return $this->authConfigId;
  }

  /**
   * Scope setter.
   */
  public function setScope($scope) {
    $this->scope = $scope;
    return $this;
  }

  /**
   * Scope getter.
   */
  public function getScope() {
    return $this->scope;
  }

  /**
   * Instance URL getter.
   */
  public function setInstanceUrl($instance_url) {
    $this->instance_url = $instance_url;
    return $this;
  }

  /**
   * Instance URL getter.
   */
  public function getInstanceUrl() {
    return $this->instance_url;
  }

  /**
   * Identity setter.
   */
  public function setIdentity($identity) {
    $this->identity = $identity;
    return $this;
  }

  /**
   * Identity getter.
   */
  public function getIdentity() {
    return $this->identity;
  }

  /**
   * Endpoint getter.
   */
  public function getEndpoint($class_name) {
    return $this->getInstanceUrl() . SalesforceAuthProviderInterface::SOAP_CLASS_PATH  . $class_name;
  }

}
