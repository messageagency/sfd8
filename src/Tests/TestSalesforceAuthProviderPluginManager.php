<?php

namespace Drupal\salesforce\Tests;

use Drupal\salesforce\Entity\SalesforceAuthConfig;
use Drupal\salesforce\SalesforceAuthProviderPluginManager;
use Drupal\salesforce\Token\SalesforceToken;

class TestSalesforceAuthProviderPluginManager extends SalesforceAuthProviderPluginManager {

  protected $hasProviders = TRUE;
  protected $hasConfig = TRUE;
  protected $hasToken = TRUE;

  public function setHasToken($hasToken) {
    $this->hasToken = $hasToken;
    return $this;
  }

  public function getToken() {
    return $this->hasToken ? new SalesforceToken() : FALSE;
  }

  public function setHasConfig($hasConfig) {
    $this->hasConfig = $hasConfig;
    return $this;
  }

  public function getConfig() {
    return $this->hasConfig ? SalesforceAuthConfig::create(['id' => 1]) : NULL;
  }

  public function setHasProviders($hasProviders) {
    $this->hasProviders = $hasProviders;
    return $this;
  }

  public function hasProviders() {
    return $this->hasProviders;
  }

  public function getProvider() {
    return new TestSalesforceAuthProvider();
  }

}
