<?php

namespace Drupal\salesforce\Consumer;

use OAuth\Common\Consumer\Credentials;

/**
 * Class SalesforceCredentials.
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
  public function getConsumerKey() {
    return $this->consumerKey;
  }

  /**
   * {@inheritdoc}
   */
  public function getLoginUrl() {
    return $this->loginUrl;
  }

}
