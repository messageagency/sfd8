<?php

namespace Drupal\salesforce\Consumer;

/**
 * OAuth user agent credentials.
 */
class OAuthCredentials extends SalesforceCredentials {

  /**
   * {@inheritdoc}
   */
  public function __construct($consumerKey, $loginUrl, $consumerSecret) {
    parent::__construct($consumerKey, $loginUrl);
    $this->consumerSecret = $consumerSecret;
  }

}
