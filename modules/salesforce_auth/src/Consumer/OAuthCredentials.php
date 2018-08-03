<?php

namespace Drupal\salesforce_auth\Consumer;

class OAuthCredentials extends SalesforceCredentials {
  public function __construct($consumerKey, $loginUrl, $consumerSecret) {
    parent::__construct($consumerKey, $loginUrl);
    $this->consumerSecret = $consumerSecret;
  }
}