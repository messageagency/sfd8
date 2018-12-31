<?php

namespace Drupal\salesforce_encrypt\Consumer;

use Drupal\salesforce\Consumer\SalesforceCredentials;

/**
 * OAuth encrypted creds.
 */
class OAuthEncryptedCredentials extends SalesforceCredentials {

  /**
   * Encryption profile id.
   *
   * @var string
   */
  protected $encryptionProfileId;

  /**
   * {@inheritdoc}
   */
  public function __construct($consumerKey, $loginUrl, $consumerSecret, $encryptionProfileId) {
    parent::__construct($consumerKey, $loginUrl, $consumerSecret);
    $this->encryptionProfileId = $encryptionProfileId;
  }

  /**
   * Getter.
   *
   * @return string
   *   The encryption profile id.
   */
  public function getEncryptionProfileId() {
    return $this->encryptionProfileId;
  }

}
