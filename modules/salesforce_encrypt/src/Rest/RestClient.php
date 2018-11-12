<?php

namespace Drupal\salesforce_encrypt\Rest;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\salesforce\EntityNotFoundException;
use Drupal\salesforce\Rest\RestClient as SalesforceRestClient;
use Drupal\salesforce\SalesforceAuthProviderPluginManager;
use GuzzleHttp\ClientInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Objects, properties, and methods to communicate with the Salesforce REST API.
 *
 * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
 */
class RestClient extends SalesforceRestClient implements EncryptedRestClientInterface {

  use StringTranslationTrait;

  protected $encryption;
  protected $encryptionProfileManager;
  protected $encryptionProfileId;

  /**
   * The encryption profile to use when encrypting and decrypting data.
   *
   * @var \Drupal\encrypt\EncryptionProfileInterface
   */
  protected $encryptionProfile;

  /**
   * Construct a new Encrypted Rest Client.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The GuzzleHttp Client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \Drupal\Component\Serialization\Json $json
   *   The JSON serializer service.
   * @param \Drupal\encrypt\EncryptServiceInterface $encryption
   *   The encryption service.
   * @param \Drupal\encrypt\EncryptionProfileManagerInterface $encryptionProfileManager
   *   The Encryption profile manager service.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend service.
   */
  public function __construct__construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, StateInterface $state, CacheBackendInterface $cache, Json $json, TimeInterface $time, SalesforceAuthProviderPluginManager $auth, EncryptServiceInterface $encryption, EncryptionProfileManagerInterface $encryptionProfileManager, LockBackendInterface $lock) {
    parent::__construct($http_client, $config_factory, $state, $cache, $json, $time, $auth);
    $this->encryption = $encryption;
    $this->encryptionProfileId = $this->state->get('salesforce_encrypt.profile');
    $this->encryptionProfileManager = $encryptionProfileManager;
    $this->encryptionProfile = NULL;
    $this->lock = $lock;
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
   */
  public function enableEncryption(EncryptionProfileInterface $profile) {
    if ($ret = $this->setEncryption($profile)) {
      $this->state->resetCache();
    }
    return $ret;
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
   */
  public function disableEncryption() {
    if ($ret = $this->setEncryption()) {
      $this->state->resetCache();
    }
    return $ret;
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
   */
  public function hookEncryptionProfileDelete(EncryptionProfileInterface $profile) {
    if ($this->encryptionProfileId == $profile->id()) {
      $this->disableEncryption();
    }
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
   */
  protected function setEncryption(EncryptionProfileInterface $profile = NULL) {
    if (!$this->lock->acquire('salesforce_encrypt')) {
      throw new \RuntimeException('Unable to acquire lock.');
    }

    $access_token = $this->getAccessToken();
    $refresh_token = $this->getRefreshToken();
    $identity = $this->getIdentity();
    $consumerKey = $this->getConsumerKey();
    $consumerSecret = $this->getConsumerSecret();

    $this->encryptionProfileId = $profile == NULL ? NULL : $profile->id();
    $this->encryptionProfile = $profile;
    $this->state->set('salesforce_encrypt.profile', $this->encryptionProfileId);

    $this->setAccessToken($access_token);
    $this->setRefreshToken($refresh_token);
    $this->setIdentity($identity);
    $this->setConsumerKey($consumerKey);
    $this->setConsumerSecret($consumerSecret);

    $this->lock->release('salesforce_encrypt');
  }

  /**
   * {@inheritdoc}
   */
  public function getEncryptionProfile() {
    if ($this->encryptionProfile) {
      return $this->encryptionProfile;
    }
    elseif (empty($this->encryptionProfileId)) {
      return NULL;
    }
    else {
      $this->encryptionProfile = $this->encryptionProfileManager
        ->getEncryptionProfile($this->encryptionProfileId);
      if (empty($this->encryptionProfile)) {
        throw new EntityNotFoundException(['id' => $this->encryptionProfileId], 'encryption_profile');
      }
      return $this->encryptionProfile;
    }
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
   */
  protected function _getEncryptionProfile() {
    try {
      $profile = $this->getEncryptionProfile();
    }
    catch (EntityNotFoundException $e) {
      drupal_set_message($this->t('Error while loading encryption profile. You will need to <a href=":encrypt">assign a new encryption profile</a>, then <a href=":oauth">re-authenticate to Salesforce</a>.', [':encrypt' => Url::fromRoute('salesforce_encrypt.settings')->toString(), ':oauth' => Url::fromRoute('salesforce.admin_config_salesforce')->toString()]), 'error');
    }

    return $profile;
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
   */
  public function encrypt($value) {
    if (empty($this->_getEncryptionProfile())) {
      return $value;
    }
    else {
      return $this->encryption->encrypt($value, $this->_getEncryptionProfile());
    }
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
   */
  public function decrypt($value) {
    if (empty($this->_getEncryptionProfile()) || empty($value) || mb_strlen($value) === 0) {
      return $value;
    }
    else {
      return $this->encryption->decrypt($value, $this->_getEncryptionProfile());
    }
  }

}
