<?php

namespace Drupal\salesforce_jwt\Consumer;

use Drupal\salesforce\Consumer\SalesforceCredentials;

class JWTCredentials extends SalesforceCredentials {
  protected $loginUser;
  protected $encryptKeyId;
  public function __construct($consumerKey, $loginUrl, $loginUser, $encryptKeyId) {
    parent::__construct($consumerKey, $loginUrl);
    $this->loginUser = $loginUser;
    $this->encryptKeyId = $encryptKeyId;
  }
  public function getLoginUser() {
    return $this->loginUser;
  }
  public function getEncryptKeyId() {
    return $this->encryptKeyId;
  }
}