<?php

namespace Drupal\salesforce_auth\Consumer;

class JWTCredentials extends SalesforceCredentials {
  protected $loginUser;
  protected $encryptKeyId;
  public function __construct($consumerId, $loginUrl, $loginUser, $encryptKeyId) {
    parent::__construct($consumerId, $loginUrl);
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