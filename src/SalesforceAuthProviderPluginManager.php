<?php

namespace Drupal\salesforce;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use \Drupal\salesforce\Entity\SalesforceAuthConfig as SalesforceAuthEntity;
use Drupal\salesforce\Entity\SalesforceAuthConfig;
use OAuth\Common\Storage\Exception\TokenNotFoundException;
use OAuth\OAuth2\Token\StdOAuth2Token;


class SalesforceAuthProviderPluginManager extends DefaultPluginManager {

  protected $config;

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
    parent::__construct('Plugin/SalesforceAuthProvider', $namespaces, $module_handler, 'Drupal\salesforce\SalesforceAuthProviderInterface');
    $this->alterInfo('salesforce_auth_provider_info');
    $this->setCacheBackend($cache_backend, 'salesforce_auth_provider');
    $this->etm = $etm;
  }

  /**
   * @deprecated interim, do not use.
   */
  public static function updateAuthConfig() {
    $oauth = self::getAuthConfig();
    $config = \Drupal::configFactory()->getEditable('salesforce.settings');
    $settings = [
      'consumer_key' => $config->get('consumer_key'),
      'consumer_secret' => $config->get('consumer_secret'),
      'login_url' => $config->get('login_url'),
    ];
    $oauth
      ->set('provider_settings', $settings)
      ->save();
  }

  /**
   * @deprecated interim, do not use.
   */
  public static function getAuthConfig() {
    $config = \Drupal::configFactory()->getEditable('salesforce.settings');
    $auth_provider = $config->get('salesforce_auth_provider');
    if (!$auth_provider || !$oauth = SalesforceAuthConfig::load($auth_provider)) {
      // config to new plugin config system.
      $values = [
        'id' => 'oauth_default',
        'label' => 'OAuth Default',
        'provider' => 'oauth',
      ];
      $oauth = SalesforceAuthConfig::create($values);
      $config
        ->set('salesforce_auth_provider', 'oauth_default')
        ->save();
    }
    return $oauth;
  }

  protected function authStorage() {
    if (empty($this->authStorage)) {
      $this->authStorage = $this->etm->getStorage('salesforce_auth');
    }
    return $this->authStorage;
  }

  public function getProviders() {
    return $this->authStorage()->loadMultiple();
  }

  public function hasProviders() {
    return $this->authStorage()->hasData();
  }

  /**
   * Get the active auth service provider, or null if it has not been assigned.
   *
   * @return \Drupal\salesforce\Entity\SalesforceAuthConfig
   */
  public function getConfig() {
    $provider_id = $this->config()->get('salesforce_auth_provider');
    if (empty($provider_id)) {
      return NULL;
    }
    return SalesforceAuthEntity::load($provider_id);
  }

  /**
   * @return \Drupal\salesforce\SalesforceAuthProviderInterface|null
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

  protected function config() {
    if (!$this->config) {
      $this->config = \Drupal::config('salesforce.settings');
    }
    return $this->config;
  }

}