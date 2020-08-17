<?php

namespace Drupal\salesforce\Storage;

use Drupal\Core\State\StateInterface;
use OAuth\Common\Storage\Exception\TokenNotFoundException;
use OAuth\Common\Token\TokenInterface;

/**
 * Salesforce auth token storage.
 */
class SalesforceAuthTokenStorage implements SalesforceAuthTokenStorageInterface {

  const TOKEN_STORAGE_PREFIX = "salesforce.auth_tokens";
  const AUTH_STATE_STORAGE_PREFIX = "salesforce.auth_state";
  const IDENTITY_STORAGE_PREFIX = "salesforce.auth_identity";

  /**
   * State kv storage.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * SalesforceAuthTokenStorage constructor.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * Token storage key for given service.
   *
   * @return string
   *   Token storage key for given service.
   */
  protected static function getTokenStorageId($service) {
    return static::TOKEN_STORAGE_PREFIX . '.' . $service;
  }

  /**
   * Auth state storage key for given service.
   *
   * @return string
   *   Auth state storage key for given service.
   */
  protected static function getAuthStateStorageId($service) {
    return static::AUTH_STATE_STORAGE_PREFIX . '.' . $service;
  }

  /**
   * Identity storage key for given service.
   *
   * @return string
   *   Identity storage key for given service.
   */
  protected static function getIdentityStorageId($service) {
    return static::IDENTITY_STORAGE_PREFIX . '.' . $service;
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveAccessToken($service) {
    if ($token = $this->state->get(static::getTokenStorageId($service))) {
      return $token;
    }
    throw new TokenNotFoundException();
  }

  /**
   * {@inheritdoc}
   */
  public function storeAccessToken($service, TokenInterface $token) {
    // Salesforce API doesn't return a refresh token when refreshing.
    // If $token refresh token is null, retain existing instead of overwriting.
    if (!$token->getRefreshToken()) {
      $oldToken = $this->state->get(static::getTokenStorageId($service));
      if ($oldToken) {
        $token->setRefreshToken($oldToken->getRefreshToken());
      }
    }
    $this->state->set(static::getTokenStorageId($service), $token);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAccessToken($service) {
    try {
      return !empty($this->retrieveAccessToken($service));
    }
    catch (TokenNotFoundException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function clearToken($service) {
    $this->state->delete(static::getTokenStorageId($service));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clearAllTokens() {
    // noop. We don't do this. Only here to satisfy interface.
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function storeAuthorizationState($service, $state) {
    $this->state->set(static::getAuthStateStorageId($service), $state);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasAuthorizationState($service) {
    return !empty($this->retrieveAuthorizationState($service));
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveAuthorizationState($service) {
    return $this->state->get(static::getAuthStateStorageId($service));
  }

  /**
   * {@inheritdoc}
   */
  public function clearAuthorizationState($service) {
    $this->state->delete(static::getAuthStateStorageId($service));
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function clearAllAuthorizationStates() {
    // noop. only here to satisfy interface. Use clearAuthorizationState().
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function storeIdentity($service, $identity) {
    $this->state->set(static::getIdentityStorageId($service), $identity);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasIdentity($service) {
    return !empty($this->retrieveIdentity($service));
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveIdentity($service) {
    return $this->state->get(static::getIdentityStorageId($service));
  }

  /**
   * {@inheritdoc}
   */
  public function clearIdentity($service) {
    $this->state->delete(static::getIdentityStorageId($service));
    return $this;
  }

}
