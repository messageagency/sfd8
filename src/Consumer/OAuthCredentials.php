<?php

namespace Drupal\salesforce\Consumer;

use Drupal\salesforce\Plugin\SalesforceAuthProvider\SalesforceOAuthPlugin;

class OAuthCredentials extends SalesforceCredentials {
  public function __construct($consumerKey, $loginUrl, $consumerSecret) {
    parent::__construct($consumerKey, $loginUrl);
    $this->consumerSecret = $consumerSecret;
  }

  /**
   * {@inheritdoc}
   */
  public function getCallbackUrl() {
    return SalesforceOAuthPlugin::getAuthCallbackUrl();
  }

}