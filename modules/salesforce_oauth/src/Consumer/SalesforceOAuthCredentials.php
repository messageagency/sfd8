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
   * Constructor helper.
   *
   * @param array $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\salesforce_oauth\Consumer\SalesforceOAuthCredentials
   *   Credentials, valid or not.
   */
  public static function create(array $configuration) {
    return new static($configuration['consumer_key'], $configuration['consumer_secret'], $configuration['login_url']);
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

  /**
   * {@inheritdoc}
   */
  public function isValid() {
    return !empty($this->loginUrl) && !empty($this->consumerSecret) && !empty($this->consumerId);
  }

}
