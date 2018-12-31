<?php

namespace Drupal\salesforce\Storage;

use Drupal\Core\State\StateInterface;
use Drupal\salesforce\SalesforceAuthProviderPluginManager;
use Drupal\salesforce\Token\SalesforceToken;
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
   * Backwards-compatibility for legacy singleton auth.
   *
   * @return string
   *   Id of the active oauth.
   *
   * @deprecated BC legacy auth scheme only, do not use, will be removed.
   */
  private function service() {
    $oauth = SalesforceAuthProviderPluginManager::getAuthConfig();
    return $oauth->id();
  }

  /**
   * Backwards-compatibility for legacy singleton auth.
   *
   * @deprecated BC legacy auth scheme only, do not use, will be removed.
   */
  public function updateToken() {
    $this->storeAccessToken($this->service(),
      new SalesforceToken(
        $this->state->get('salesforce.access_token'),
        $this->state->get('salesforce.refresh_token')));
    return $this;
  }

  /**
   * Backwards-compatibility for legacy singleton auth.
   *
   * @deprecated BC legacy auth scheme only, do not use, will be removed.
   */
  public function updateIdentity() {
    $this->storeIdentity($this->service(), $this->state->get('salesforce.identity'));
    return $this;
  }

  /**
   * Token storage key for given service.
   *
   * @return string
   *   Token storage key for given service.
   */
  protected static function getTokenStorageId($service) {
    return self::TOKEN_STORAGE_PREFIX . '.' . $service;
  }

  /**
   * Auth state storage key for given service.
   *
   * @return string
   *   Auth state storage key for given service.
   */
  protected static function getAuthStateStorageId($service) {
    return self::AUTH_STATE_STORAGE_PREFIX . '.' . $service;
  }

  /**
   * Identity storage key for given service.
   *
   * @return string
   *   Identity storage key for given service.
   */
  protected static function getIdentityStorageId($service) {
    return self::IDENTITY_STORAGE_PREFIX . '.' . $service;
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveAccessToken($service) {
    if ($token = $this->state->get(self::getTokenStorageId($service))) {
      return $token;
    }
    throw new TokenNotFoundException();
  }

  /**
   * {@inheritdoc}
   */
  public function storeAccessToken($service, TokenInterface $token) {
    $this->state->set(self::getTokenStorageId($service), $token);
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
    $this->state->delete(self::getTokenStorageId($service));
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
    $this->state->set(self::getAuthStateStorageId($service), $state);
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
    return $this->state->get(self::getAuthStateStorageId($service));
  }

  /**
   * {@inheritdoc}
   */
  public function clearAuthorizationState($service) {
    $this->state->delete(self::getAuthStateStorageId($service));
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
    $this->state->set(self::getIdentityStorageId($service), $identity);
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
    return $this->state->get(self::getIdentityStorageId($service));
  }

  /**
   * {@inheritdoc}
   */
  public function clearIdentity($service) {
    $this->state->delete(self::getIdentityStorageId($service));
    return $this;
  }

}
