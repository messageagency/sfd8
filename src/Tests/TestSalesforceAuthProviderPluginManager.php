<?php

namespace Drupal\salesforce\Tests;

use Drupal\salesforce\SalesforceAuthProviderPluginManager;

class TestSalesforceAuthProviderPluginManager extends SalesforceAuthProviderPluginManager {

  public function getProvider() {
    return new TestSalesforceAuthProvider();
  }
}