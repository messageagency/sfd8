<?php

namespace Drupal\salesforce_encrypt\Consumer;

use Drupal\salesforce\Consumer\SalesforceCredentials;
use Drupal\salesforce\Entity\SalesforceAuthConfig;
use Drupal\salesforce\EntityNotFoundException;
use Drupal\salesforce_encrypt\Plugin\SalesforceAuthProvider\SalesforceEncryptedOAuthPlugin;

class OAuthEncryptedCredentials extends SalesforceCredentials {

  protected $encryptionProfileId;

  public function __construct($consumerKey, $loginUrl, $consumerSecret, $encryptionProfileId) {
    parent::__construct($consumerKey, $loginUrl);
    $this->consumerSecret = $consumerSecret;
    $this->encryptionProfileId = $encryptionProfileId;
  }

  /**
   * @return string
   */
  public function getEncryptionProfileId() {
    return $this->encryptionProfileId;
  }

  /**
   * {@inheritdoc}
   */
  public function getCallbackUrl() {
    return SalesforceEncryptedOAuthPlugin::getAuthCallbackUrl();
  }

}