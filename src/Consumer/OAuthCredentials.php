<?php

namespace Drupal\salesforce\Consumer;

class OAuthCredentials extends SalesforceCredentials {

  /**
   * {@inheritdoc}
   */
  public function __construct($consumerKey, $loginUrl, $consumerSecret) {
    parent::__construct($consumerKey, $loginUrl);
    $this->consumerSecret = $consumerSecret;
  }
}