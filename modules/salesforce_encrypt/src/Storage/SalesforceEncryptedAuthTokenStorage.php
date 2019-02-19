<?php

namespace Drupal\salesforce_encrypt\Storage;

use Drupal\salesforce\Storage\SalesforceAuthTokenStorage;
use OAuth\Common\Token\TokenInterface;

/**
 * Auth token storage, using encryption.
 */
class SalesforceEncryptedAuthTokenStorage extends SalesforceAuthTokenStorage {

  /**
   * Auth plugin manager.
   *
   * @var \Drupal\salesforce\SalesforceAuthProviderPluginManager
   */
  protected $authPluginManager;

  /**
   * {@inheritdoc}
   */
  public function retrieveAccessToken($service_id) {
    $token = parent::retrieveAccessToken($service_id);
    if ($token instanceof TokenInterface || !$this->service($service_id) instanceof SalesforceEncryptedOAuthPlugin) {
      return $token;
    }
    $token = unserialize($this->service($service_id)->decrypt($token));
    return $token;
  }

  /**
   * {@inheritdoc}
   */
  public function storeAccessToken($service_id, TokenInterface $token) {
    if ($this->service($service_id) instanceof SalesforceEncryptedOAuthPlugin) {
      $token = $this->service($service_id)->encrypt(serialize($token));
    }
    $this->state->set(self::getTokenStorageId($service_id), $token);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function storeIdentity($service_id, $identity) {
    if ($this->service($service_id) instanceof SalesforceEncryptedOAuthPlugin) {
      if (is_array($identity)) {
        $identity = serialize($identity);
      }
      $identity = $this->service($service_id)->encrypt($identity);
    }
    $this->state->set(self::getIdentityStorageId($service_id), $identity);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveIdentity($service_id) {
    $identity = parent::retrieveIdentity($service_id);
    if (!$this->service($service_id) instanceof SalesforceEncryptedOAuthPlugin) {
      return $identity;
    }
    $identity = $this->service($service_id)->decrypt($identity);
    if (!empty($identity) && !is_array($identity)) {
      $identity = unserialize($identity);
    }
    return $identity;
  }

}
