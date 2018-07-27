<?php

namespace Drupal\salesforce_auth\Consumer;

class OAuthCredentials extends SalesforceCredentials {
  public function __construct($consumerId, $loginUrl, $consumerSecret) {
    parent::__construct($consumerId, $loginUrl);
    $this->consumerSecret = $consumerSecret;
  }
}