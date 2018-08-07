<?php

namespace Drupal\salesforce_auth\Consumer;

use Drupal\salesforce_auth\SalesforceAuthProviderInterface;
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