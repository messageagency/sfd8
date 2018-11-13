<?php

namespace Drupal\salesforce_encrypt;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\salesforce\Entity\SalesforceAuthConfig;
use Drupal\salesforce\EntityNotFoundException;
use Drupal\salesforce\Storage\SalesforceAuthTokenStorage;
use Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface;
use Drupal\salesforce_encrypt\Plugin\SalesforceAuthProvider\SalesforceEncryptedOAuthPlugin;
use OAuth\Common\Token\TokenInterface;

class SalesforceEncryptedAuthTokenStorage extends SalesforceAuthTokenStorage implements SalesforceEncryptedAuthTokenStorageInterface {

  /**
   * @var \Drupal\salesforce\SalesforceAuthProviderPluginManager
   */
  protected $authPluginManager;

  /**
   * @param $service_id
   *
   * @return \Drupal\salesforce\SalesforceAuthProviderPluginInterface
   */
  protected function service($service_id) {
    if (!$this->authPluginManager) {
      $this->authPluginManager = \Drupal::service('plugin.manager.salesforce.auth_providers');
    }
    $auth = SalesforceAuthConfig::load($service_id);
    return $auth->getPlugin();
  }

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