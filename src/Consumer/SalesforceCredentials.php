<?php

namespace Drupal\salesforce\Consumer;

use Drupal\Core\Url;
use OAuth\Common\Consumer\Credentials;

/**
 * Salesforce credentials extension, for drupalisms.
 */
abstract class SalesforceCredentials extends Credentials implements SalesforceCredentialsInterface {

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
  public function __construct($consumerKey, $loginUrl) {
    parent::__construct($consumerKey, NULL, NULL);
    $this->loginUrl = $loginUrl;
    $this->consumerKey = $consumerKey;
  }

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

}
