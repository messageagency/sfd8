<?php

namespace Drupal\salesforce\Consumer;

use Drupal\salesforce\SalesforceAuthProviderInterface;
use OAuth\Common\Consumer\Credentials;

abstract class SalesforceCredentials extends Credentials implements SalesforceCredentialsInterface {

  protected $loginUrl;
  protected $consumerKey;
  public function __construct($consumerKey, $loginUrl) {
    parent::__construct($consumerKey, NULL, NULL);
    $this->loginUrl = $loginUrl;
    $this->consumerKey = $consumerKey;
  }

  public function getConsumerKey() {
    return $this->consumerKey;
  }

  public function getLoginUrl() {
    return $this->loginUrl;
  }

}