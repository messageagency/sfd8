<?php

namespace Drupal\salesforce_push;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin type manager for SF push queue processors
 */
class PushQueueProcessorPluginManager extends DefaultPluginManager {

  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/SalesforcePushQueueProcessor', $namespaces, $module_handler);

    $this->setCacheBackend($cache_backend, 'salesforce_push_queue_processor');
  }
}
