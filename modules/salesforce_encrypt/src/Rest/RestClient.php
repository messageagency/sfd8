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
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, StateInterface $state, CacheBackendInterface $cache, Json $json, TimeInterface $time, SalesforceAuthProviderPluginManager $auth, EncryptServiceInterface $encryption, EncryptionProfileManagerInterface $encryptionProfileManager, LockBackendInterface $lock) {
    parent::__construct($http_client, $config_factory, $state, $cache, $json, $time, $auth);
    $this->encryption = $encryption;
    $this->encryptionProfileId = $this->state->get('salesforce_encrypt.profile');
    $this->encryptionProfileManager = $encryptionProfileManager;
    $this->encryptionProfile = NULL;
    $this->lock = $lock;
  }

  /**
   * Encrypts all sensitive salesforce config values.
   *
   * @throws RuntimeException if Salesforce if encryption was not enabled.
   */
  public function enableEncryption(EncryptionProfileInterface $profile) {
    if ($ret = $this->setEncryption($profile)) {
      $this->state->resetCache();
    }
    return $ret;
  }

  /**
   * Inverse of ::enableEncryption. Decrypts all sensitive salesforce config
   * values.
   *
   * @throws RuntimeException if Salesforce encryption can't be disabled
   */
  public function disableEncryption() {
    if ($ret = $this->setEncryption()) {
      $this->state->resetCache();
    }
    return $ret;
  }

  /**
   *
   */
  public function hookEncryptionProfileDelete(EncryptionProfileInterface $profile) {
    if ($this->encryptionProfileId == $profile->id()) {
      $this->disableEncryption();
    }
  }

  /**
   *
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
   * Exception-handling wrapper around getEncryptionProfile().
   *
   * getEncryptionProfile() will throw an EntityNotFoundException exception
   * if it has an encryption profile ID but cannot load it.  In this wrapper
   * we handle that exception by setting a helpful error message and allow
   * execution to proceed.
   *
   * @return \Drupal\encrypt\EncryptionProfileInterface | NULL
   *   The encryption profile if it can be loaded, otherwise NULL.
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
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function decrypt($value) {
    if (empty($this->_getEncryptionProfile()) || empty($value) || Unicode::strlen($value) === 0) {
      return $value;
    }
    else {
      return $this->encryption->decrypt($value, $this->_getEncryptionProfile());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken() {
    return $this->decrypt(parent::getAccessToken());
  }

  /**
   * {@inheritdoc}
   */
  public function setAccessToken($token) {
    return parent::setAccessToken($this->encrypt($token));
  }

  /**
   * {@inheritdoc}
   */
  public function getRefreshToken() {
    return $this->decrypt(parent::getRefreshToken());
  }

  /**
   * {@inheritdoc}
   */
  public function setRefreshToken($token) {
    return parent::setRefreshToken($this->encrypt($token));
  }

  /**
   * {@inheritdoc}
   */
  public function setIdentity($data) {
    if (is_array($data)) {
      $data = serialize($data);
    }
    return parent::setIdentity($this->encrypt($data));
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentity() {
    $data = $this->decrypt(parent::getIdentity());
    if (!empty($data) && !is_array($data)) {
      $data = unserialize($data);
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getConsumerKey() {
    return $this->decrypt(parent::getConsumerKey());
  }

  /**
   * {@inheritdoc}
   */
  public function setConsumerKey($value) {
    return parent::setConsumerKey($this->encrypt($value));
  }

  /**
   * {@inheritdoc}
   */
  public function getConsumerSecret() {
    return $this->decrypt(parent::getConsumerSecret());
  }

  /**
   * {@inheritdoc}
   */
  public function setConsumerSecret($value) {
    return parent::setConsumerSecret($this->encrypt($value));
  }

}
