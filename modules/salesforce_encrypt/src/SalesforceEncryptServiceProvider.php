<?php

namespace Drupal\salesforce_encrypt;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the salesforce client service.
 */
class SalesforceEncryptServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container->getDefinition('salesforce.auth_token_storage')
      ->setClass(SalesforceEncryptedAuthTokenStorage::CLASS);
  }

}
