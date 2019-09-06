<?php

namespace Drupal\salesforce\Consumer;

use OAuth\Common\Consumer\Credentials;

/**
 * Salesforce credentials extension, for drupalisms.
 */
class SalesforceCredentials extends Credentials implements SalesforceCredentialsInterface {

  /**
   * Login URL e.g. https://test.salesforce.com or https://login.salesforce.com.
   *
   * @var string
   */
  protected $loginUrl;

  /**
   * Consumer key for the Salesforce connected OAuth app.
   *
   * @var string
   */
  protected $consumerKey;

  /**
   * {@inheritdoc}
   */
  public function getConsumerKey() {
    return $this->consumerKey;
  }

  /**
   * {@inheritdoc}
   */
  public function getLoginUrl() {
    return $this->loginUrl;
  }

  /**
   * {@inheritdoc}
   */
  public function getCallbackUrl() {
    return Url::fromRoute('salesforce.oauth_callback', [], [
      'absolute' => TRUE,
      'https' => TRUE,
    ])->toString();
  }

  public function isValid() {
    // This class is a stub.
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(array $configuration) {
    return new static($configuration['consumer_key'], $configuration['consumer_secret'], NULL);
  }

}
