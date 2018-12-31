<?php

namespace Drupal\salesforce_encrypt;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\salesforce_encrypt\Rest\RestClient;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies the salesforce client service.
 *
 * @deprecated will be removed in 8.x-4.0 release.
 */
class SalesforceEncryptServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Overrides salesforce.client class with our EncryptedRestClientInterface.
    $container->getDefinition('salesforce.client')
      ->setClass(RestClient::class)
      ->addArgument(new Reference('encryption'))
      ->addArgument(new Reference('encrypt.encryption_profile.manager'))
      ->addArgument(new Reference('lock'));
  }

}
