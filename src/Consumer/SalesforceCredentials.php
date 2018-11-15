<?php

namespace Drupal\salesforce\Consumer;

use Drupal\Core\Url;
use OAuth\Common\Consumer\Credentials;

abstract class SalesforceCredentials extends Credentials implements SalesforceCredentialsInterface {

  protected $loginUrl;
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