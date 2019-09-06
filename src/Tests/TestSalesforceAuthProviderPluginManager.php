<?php

namespace Drupal\salesforce\Tests;

use Drupal\salesforce\SalesforceAuthProviderPluginManager;
use Drupal\salesforce\Token\SalesforceToken;

class TestSalesforceAuthProviderPluginManager extends SalesforceAuthProviderPluginManager {

  public function getProvider() {
    return new TestSalesforceAuthProvider();
  }

  public function getToken() {
    return new SalesforceToken();
  }
}