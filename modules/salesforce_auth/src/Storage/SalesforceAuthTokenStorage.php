<?php

namespace Drupal\salesforce_auth\Storage;

use Drupal\Core\State\StateInterface;
use OAuth\Common\Storage\Exception\TokenNotFoundException;
use OAuth\Common\Token\TokenInterface;

class SalesforceAuthTokenStorage implements SalesforceAuthTokenStorageInterface {

  const TOKEN_STORAGE_PREFIX = "salesforce_auth.tokens";
  const AUTH_STATE_STORAGE_PREFIX = "salesforce_auth.auth_state";
  const IDENTITY_STORAGE_PREFIX = "salesforce_auth.identity";

  protected $state;

  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  protected static function getTokenStorageId($service) {
    return self::TOKEN_STORAGE_PREFIX . '.' . $service;
  }

  protected static function getAuthStateStorageId($service) {
    return self::AUTH_STATE_STORAGE_PREFIX . '.' . $service;
  }

  protected static function getIdentityStorageId($service) {
    return self::IDENTITY_STORAGE_PREFIX . '.' . $service;
  }

  /**
   *{@inheritdoc}
   */
  public function retrieveAccessToken($service) {
    if ($token = $this->state->get(self::getTokenStorageId($service))) {
      return $token;
    }
    throw new TokenNotFoundException();
  }

  /**
   *{@inheritdoc}
   */
  public function storeAccessToken($service, TokenInterface $token) {
    $this->state->set(self::getTokenStorageId($service), $token);
    return $this;
  }

  /**
   *{@inheritdoc}
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
   *{@inheritdoc}
   */

  public function clearToken($service) {
    $this->state->delete(self::getTokenStorageId($service));
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function clearAllTokens() {
    // noop. We don't do this. Only here to satisfy interface.
  }

  /**
   *{@inheritdoc}
   */
  public function storeAuthorizationState($service, $state) {
    $this->state->set(self::getAuthStateStorageId($service), $state);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function hasAuthorizationState($service) {
    return !empty($this->retrieveAuthorizationState($service));
  }

  /**
   *{@inheritdoc}
   */
  public function retrieveAuthorizationState($service) {
    return $this->state->get(self::getAuthStateStorageId($service));
  }

  /**
   *{@inheritdoc}
   */
  public function clearAuthorizationState($service) {
    $this->state->delete(self::getAuthStateStorageId($service));
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function clearAllAuthorizationStates() {
    // noop. only here to satisfy interface. Use clearAuthorizationState().
  }

  /**
   *{@inheritdoc}
   */
  public function storeIdentity($service, $identity) {
    $this->state->set(self::getIdentityStorageId($service), $identity);
    return $this;
  }

  /**
   *{@inheritdoc}
   */
  public function hasIdentity($service) {
    return !empty($this->retrieveIdentity($service));
  }

  /**
   *{@inheritdoc}
   */
  public function retrieveIdentity($service) {
    return $this->state->get(self::getIdentityStorageId($service));
  }

  /**
   *{@inheritdoc}
   */
  public function clearIdentity($service) {
    $this->state->delete(self::getIdentityStorageId($service));
    return $this;
  }

}
