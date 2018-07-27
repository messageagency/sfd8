<?php

namespace Drupal\salesforce_jwt;

use Drupal\salesforce_auth\AuthProviderInterface;
use Drupal\salesforce_auth\AuthTokenInterface;
use Drupal\salesforce_jwt\Entity\JWTAuthConfig;

/**
 * Represents an auth token (with lookups to correlated project config).
 */
class AuthToken implements AuthTokenInterface {

  protected $token;

  protected $authConfigId;

  protected $authConfigLabel;

  protected $identity;

  /**
   * The config for this project.
   *
   * @var \Drupal\salesforce_jwt\Entity\JWTAuthConfig
   */
  protected $config;

  /**
   * Create a new AuthToken stub from the given project.
   */
  public function __construct($authConfigId) {
    $this->authConfigId = $authConfigId;
    $this->config = JWTAuthConfig::load($authConfigId);
  }

  /**
   * Get the project label from correlated config.
   */
  public function label() {
    return $this->config->label();
  }

  /**
   * Token setter.
   */
  public function setToken($token) {
    $this->token = $token;
  }

  /**
   * Identity setter.
   */
  public function setIdentity($identity) {
    $this->identity = $identity;
  }

  /**
   * Identity getter.
   */
  public function getIdentity() {
    return $this->identity;
  }

  /**
   * Project id getter.
   */
  public function getAuthConfigId() {
    return $this->authConfigId;
  }

  /**
   * Access token getter.
   */
  public function getAccessToken() {
    return $this->token->access_token;
  }

  /**
   * Refresh token getter. N/A for JWT auth.
   */
  public function getRefreshToken() {
    return NULL;
  }

  /**
   * Scope getter.
   */
  public function getScope() {
    return $this->token->scope;
  }

  /**
   * Instance URL getter.
   */
  public function getInstanceUrl() {
    return $this->token->instance_url;
  }

  /**
   * Id getter.
   */
  public function id() {
    return $this->token->id;
  }

  /**
   * Endpoint getter.
   */
  public function getEndpoint($class_name) {
    return $this->getInstanceUrl() . AuthProviderInterface::SOAP_CLASS_PATH . $class_name;
  }

}
