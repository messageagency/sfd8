<?php

namespace Drupal\salesforce_auth;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

class SalesforceAuthProviderPluginManager extends DefaultPluginManager {

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
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/SalesforceAuthProvider', $namespaces, $module_handler, 'Drupal\salesforce_auth\SalesforceAuthProviderPluginInterface', 'Drupal\salesforce_auth\Annotation\SalesforceAuthProvider');
    $this->alterInfo('salesforce_auth_provider_info');
    $this->setCacheBackend($cache_backend, 'salesforce_auth_provider');
  }

}