<?php

namespace Drupal\salesforce\Tests;

use Drupal\salesforce\Entity\SalesforceAuthConfig;
use Drupal\salesforce\SalesforceAuthProviderPluginManager;
use Drupal\salesforce\Token\SalesforceToken;

/**
 * Test auth provider plugn manager.
 */
class TestSalesforceAuthProviderPluginManager extends SalesforceAuthProviderPluginManager {

  /**
   * Allows testing provider states.
   *
   * @var bool
   */
  protected $hasProviders = TRUE;

  /**
   * Allows testing config states.
   *
   * @var bool
   */
  protected $hasConfig = TRUE;

  /**
   * Allows testing token states.
   *
   * @var bool
   */
  protected $hasToken = TRUE;

  /**
   * Test token state.
   */
  public function setHasToken($hasToken) {
    $this->hasToken = $hasToken;
    return $this;
  }

  /**
   * Get token.
   */
  public function getToken() {
    return $this->hasToken ? new SalesforceToken() : FALSE;
  }

  /**
   * Test config states.
   */
  public function setHasConfig($hasConfig) {
    $this->hasConfig = $hasConfig;
    return $this;
  }

  /**
   * Get config.
   */
  public function getConfig() {
    return $this->hasConfig ? SalesforceAuthConfig::create(['id' => 1]) : NULL;
  }

  /**
   * Set provider state.
   */
  public function setHasProviders($hasProviders) {
    $this->hasProviders = $hasProviders;
    return $this;
  }

  /**
   * Get has providers.
   */
  public function hasProviders() {
    return $this->hasProviders;
  }

  /**
   * Get a test provider.
   */
  public function getProvider() {
    return new TestSalesforceAuthProvider();
  }

}
