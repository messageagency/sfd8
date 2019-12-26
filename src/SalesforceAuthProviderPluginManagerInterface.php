<?php

namespace Drupal\salesforce;

use Drupal\Component\Plugin\Discovery\CachedDiscoveryInterface;
use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Cache\CacheableDependencyInterface;

/**
 * Auth provider plugin manager interface.
 */
interface SalesforceAuthProviderPluginManagerInterface extends PluginManagerInterface, CachedDiscoveryInterface, CacheableDependencyInterface, FallbackPluginManagerInterface {

  /**
   * All Salesforce auth providers.
   *
   * @return \Drupal\salesforce\Entity\SalesforceAuthConfig[]
   *   All auth provider plugins.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getProviders();

  /**
   * TRUE if any auth providers are defined.
   *
   * @return bool
   *   TRUE if any auth providers are defined.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function hasProviders();

  /**
   * Get the active auth service provider, or null if it has not been assigned.
   *
   * @return \Drupal\salesforce\Entity\SalesforceAuthConfig|null
   *   The active service provider, or null if it has not been assigned.
   */
  public function getConfig();

  /**
   * The auth provider plugin of the active service provider, or null.
   *
   * @return \Drupal\salesforce\SalesforceAuthProviderInterface|null
   *   The auth provider plugin of the active service provider, or null.
   */
  public function getProvider();

  /**
   * The credentials for the active auth provider plugin, or null.
   *
   * @return \Drupal\salesforce\Consumer\SalesforceCredentialsInterface|null
   *   The credentials for the active auth provider plugin, or null.
   */
  public function getCredentials();

  /**
   * Get the active token, or null if it has not been assigned.
   *
   * @return \OAuth\OAuth2\Token\TokenInterface|null
   *   The token of the plugin of the active config, or null.
   */
  public function getToken();

  /**
   * Force a refresh of the active token and return the fresh token.
   *
   * @return \OAuth\Common\Token\TokenInterface
   *   The token.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \OAuth\OAuth2\Service\Exception\MissingRefreshTokenException
   */
  public function refreshToken();

}
