<?php

namespace Drupal\salesforce_test_rest_client;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\salesforce\Tests\TestRestClient;
use Drupal\salesforce\Tests\TestHttpClientFactory;

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

  }

}
