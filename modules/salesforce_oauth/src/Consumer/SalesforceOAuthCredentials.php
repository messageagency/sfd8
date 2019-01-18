<?php

namespace Drupal\salesforce_oauth\Consumer;

use Drupal\Core\Url;
use Drupal\salesforce\Consumer\SalesforceCredentials;

/**
 * Salesforce credentials extension, for drupalisms.
 */
class SalesforceOAuthCredentials extends SalesforceCredentials {

  /**
   * {@inheritdoc}
   */
  public function __construct($consumerKey, $consumerSecret, $loginUrl) {
    parent::__construct($consumerKey, $consumerSecret, self::callbackUrl());
    $this->consumerKey = $consumerKey;
    $this->loginUrl = $loginUrl;
  }

  /**
   * {@inheritdoc}
   */
  public function getCallbackUrl() {
    return self::callbackUrl();
  }

  /**
   * Static wrapper to generate the callback url from the callback route.
   *
   * @return string
   *   The callback URL.
   */
  public static function callbackUrl() {
    return Url::fromRoute('salesforce.oauth_callback', [], [
      'absolute' => TRUE,
      'https' => TRUE,
    ])->toString();
  }

}
