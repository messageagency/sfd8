<?php

namespace Drupal\salesforce_auth;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use \Drupal\salesforce_auth\Entity\SalesforceAuthConfig as SalesforceAuthEntity;
use OAuth\Common\Storage\Exception\TokenNotFoundException;
use OAuth\OAuth2\Token\StdOAuth2Token;


class SalesforceAuthProviderPluginManager extends DefaultPluginManager {

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


  /**
   * Constructs a KeyPluginManager.
   *
   * @param string $type
   *   The plugin type.
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $etm) {
    parent::__construct('Plugin/SalesforceAuthProvider', $namespaces, $module_handler, 'Drupal\salesforce_auth\SalesforceAuthProviderInterface');
    $this->alterInfo('salesforce_auth_provider_info');
    $this->setCacheBackend($cache_backend, 'salesforce_auth_provider');
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
    return $this->getConfig()->getPlugin();
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
    if (!$provider = $config->getPlugin()) {
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
    if (!$provider = $config->getPlugin()) {
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