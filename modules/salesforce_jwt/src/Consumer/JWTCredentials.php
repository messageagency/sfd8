<?php

namespace Drupal\salesforce_jwt\Consumer;

use Drupal\salesforce\Consumer\SalesforceCredentials;

/**
 * JWT credentials.
 */
class JWTCredentials extends SalesforceCredentials {

  /**
   * Pre-authorized login user for JWT OAuth authentication.
   *
   * @var string
   */
  protected $loginUser;

  /**
   * Id of authorization key for this JWT Credential.
   *
   * @var string
   */
  protected $keyId;

  /**
   * {@inheritdoc}
   */
  public function __construct($consumerKey, $loginUrl, $loginUser, $keyId) {
    parent::__construct($consumerKey, NULL, NULL);
    $this->consumerKey = $consumerKey;
    $this->loginUrl = $loginUrl;
    $this->loginUser = $loginUser;
    $this->keyId = $keyId;
  }

  /**
   * Constructor helper.
   *
   * @param array $configuration
   *   Plugin configuration.
   *
   * @return \Drupal\salesforce_jwt\Consumer\JWTCredentials
   *   Credentials, valid or not.
   */
  public static function create(array $configuration) {
    return new static($configuration['consumer_key'], $configuration['login_url'], $configuration['login_user'], $configuration['encrypt_key']);
  }

  /**
   * Login user getter.
   *
   * @return string
   *   The login user.
   */
  public function getLoginUser() {
    return $this->loginUser;
  }

  /**
   * Authorization key getter.
   *
   * @return string
   *   The key id.
   */
  public function getKeyId() {
    return $this->keyId;
  }

  /**
   * {@inheritdoc}
   */
  public function isValid() {
    return !empty($this->loginUser) && !empty($this->consumerId) && !empty($this->keyId);
  }

}
