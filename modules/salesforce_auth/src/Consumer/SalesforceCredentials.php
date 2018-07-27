<?php

namespace Drupal\salesforce_auth\Consumer;

use Drupal\salesforce_auth\AuthProviderInterface;
use OAuth\Common\Consumer\Credentials;

abstract class SalesforceCredentials extends Credentials {

  protected $loginUrl;
  public function __construct($consumerId, $loginUrl) {
    parent::__construct($consumerId, NULL, NULL);
    $this->loginUrl = $loginUrl;
  }

  public function getLoginUrl() {
    return $this->loginUrl;
  }

}