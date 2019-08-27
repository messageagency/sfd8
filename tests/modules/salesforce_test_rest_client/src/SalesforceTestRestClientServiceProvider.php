<?php

namespace Drupal\salesforce_test_rest_client;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\salesforce\Tests\TestHttpClientWrapper;
use Drupal\salesforce\Tests\TestRestClient;
use Drupal\salesforce\Tests\TestHttpClientFactory;
use Drupal\salesforce\Tests\TestSalesforceAuthProviderPluginManager;

/**
 * Modifies the salesforce client service.
 */
class SalesforceTestRestClientServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides salesforce.client class to stub in our own fake methods.
    $container->getDefinition('http_client_factory')
      ->setClass(TestHttpClientFactory::class);
    $container->getDefinition('salesforce.client')
      ->setClass(TestRestClient::class);
    $container->getDefinition('salesforce.client')
      ->setClass(TestRestClient::class);
    $container->getDefinition('plugin.manager.salesforce.auth_providers')
      ->setClass(TestSalesforceAuthProviderPluginManager::class);
    $container->getDefinition('salesforce.http_client_wrapper')
      ->setClass(TestHttpClientWrapper::class);
  }

}
