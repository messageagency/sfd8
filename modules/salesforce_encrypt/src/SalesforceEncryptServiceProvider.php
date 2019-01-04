<?php

namespace Drupal\salesforce_encrypt;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Alter the container to include our own storage providers.
 */
class SalesforceEncryptServiceProvider {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    // Add a normalizer service for file entities.
    $service_definition = new Definition('Drupal\salesforce_encrypt\Storage\SalesforceEncryptedAuthTokenStorage', array(
      new Reference('state'),
      new Reference('encryption'),
      new Reference('encrypt.encryption_profile.manager'),
    ));
    $container->setDefinition('salesforce.auth_token_storage', $service_definition);
  }

}
