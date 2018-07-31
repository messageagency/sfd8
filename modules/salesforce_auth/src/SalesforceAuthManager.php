<?php

namespace Drupal\salesforce_auth;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use \Drupal\salesforce_auth\Entity\SalesforceAuthConfig as SalesforceAuthEntity;
use OAuth\Common\Storage\Exception\TokenNotFoundException;
use OAuth\OAuth2\Token\StdOAuth2Token;

/**
 * Class SalesforceAuthManager for salesforce_auth service.
 *
 * @package Drupal\salesforce_auth
 */
class SalesforceAuthManager {

  protected $providers;
  protected $config;
  protected $storage;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $etm;

  /**
   * Salesforce Auth storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $authStorage;

  public function __construct(EntityTypeManagerInterface $etm) {
    $this->etm = $etm;
    $this->authStorage = $etm->getStorage('salesforce_auth');
  }

  public function getProviders() {
    return $this->authStorage->loadMultiple();
  }

  public function hasProviders() {
    return $this->authStorage->hasData();
  }

  /**
   * Get the active auth service provider, or null if it has not been assigned.
   *
   * @return \Drupal\salesforce_auth\Entity\SalesforceAuthConfig
   */
  public function getConfig() {
    $provider_id = $this->config()->get('provider');
    if (empty($provider_id)) {
      return NULL;
    }
    return SalesforceAuthEntity::load($provider_id);
  }

  /**
   * @return \Drupal\salesforce_auth\SalesforceAuthProviderInterface|null
   */
  public function getProvider() {
    if (!$this->getConfig()) {
      return NULL;
    }
    return $this->getConfig()->getAuthProvider();
  }

  /**
   * Get the active token, or null if it has not been assigned.
   *
   * @return \OAuth\OAuth2\Token\TokenInterface
   */
  public function getToken() {
    if (!$config = $this->getConfig()) {
      return NULL;
    }
    if (!$provider = $config->getAuthProvider()) {
      return NULL;
    }
    try {
      return $provider->getAccessToken();
    }
    catch (TokenNotFoundException $e) {
      return NULL;
    }
  }

  /**
   * Force a refresh of the active token and return the fresh token.
   *
   * @return \OAuth\OAuth2\Token\TokenInterface|null
   */
  public function refreshToken() {
    if (!$config = $this->getConfig()) {
      return NULL;
    }
    if (!$provider = $config->getAuthProvider()) {
      return NULL;
    }
    $token = $this->getToken() ?: new StdOAuth2Token();
    return $provider->refreshAccessToken($token);
  }

  public function config() {
    if (!$this->config) {
      $this->config = \Drupal::config('salesforce_auth.settings');
    }
    return $this->config;
  }

}