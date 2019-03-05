<?php

namespace Drupal\salesforce;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\salesforce\Entity\SalesforceAuthConfig;
use OAuth\Common\Storage\Exception\TokenNotFoundException;
use OAuth\OAuth2\Token\StdOAuth2Token;

/**
 * Auth provider plugin manager.
 */
class SalesforceAuthProviderPluginManager extends DefaultPluginManager implements SalesforceAuthProviderPluginManagerInterface {

  /**
   * Config from salesforce.settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
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
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $authStorage;

  /**
   * Constructor.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   Entity type manager service.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $etm) {
    parent::__construct('Plugin/SalesforceAuthProvider', $namespaces, $module_handler, 'Drupal\salesforce\SalesforceAuthProviderInterface');
    $this->alterInfo('salesforce_auth_provider_info');
    $this->setCacheBackend($cache_backend, 'salesforce_auth_provider');
    $this->etm = $etm;
  }

  /**
   * Wrapper for salesforce_auth storage service.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   *   Storage for salesforce_auth.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function authStorage() {
    if (empty($this->authStorage)) {
      $this->authStorage = $this->etm->getStorage('salesforce_auth');
    }
    return $this->authStorage;
  }

  /**
   * {@inheritdoc}
   */
  public function getProviders() {
    return $this->authStorage()->loadMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public function hasProviders() {
    return $this->authStorage()->hasData();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig() {
    $provider_id = $this->config()->get('salesforce_auth_provider');
    if (empty($provider_id)) {
      return NULL;
    }
    return SalesforceAuthConfig::load($provider_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getProvider() {
    if (!$this->getConfig()) {
      return NULL;
    }
    return $this->getConfig()->getPlugin();
  }

  /**
   * {@inheritdoc}
   */
  public function getCredentials() {
    if (!$this->getProvider()) {
      return NULL;
    }
    return $this->getProvider()->getCredentials();
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
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

  /**
   * Wrapper for salesforce.settings config.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   *   Salesforce settings config.
   */
  protected function config() {
    if (!$this->config) {
      $this->config = \Drupal::config('salesforce.settings');
    }
    return $this->config;
  }

}
