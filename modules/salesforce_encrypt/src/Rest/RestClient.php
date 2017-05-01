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
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, StateInterface $state, CacheBackendInterface $cache, Json $json, TimeInterface $time, EncryptServiceInterface $encryption, EncryptionProfileManagerInterface $encryptionProfileManager, LockBackendInterface $lock) {
    parent::__construct($http_client, $config_factory, $state, $cache, $json, $time);
    $this->encryption = $encryption;
    $this->encryptionProfileId = $state->get('salesforce_encrypt.profile');
    $this->encryptionProfileManager = $encryptionProfileManager;
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

  public function hookEncryptionProfileDelete(EncryptionProfileInterface $profile) {
    if ($this->encryptionProfileId == $profile->id()) {
      $this->disableEncryption();
    }
  }

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
    if (empty($this->encryptionProfileId)) {
      return NULL;
    }
    $profile = $this
      ->encryptionProfileManager
      ->getEncryptionProfile($this->encryptionProfileId);
    if (empty($profile)) {
      throw new EntityNotFoundException(['id' => $this->encryptionProfileId], 'encryption_profile');
    }
    return $profile;
  }

  protected function getDecrypted($key) {
    $value = $this->state->get($key);
    try {
      $profile = $this->getEncryptionProfile();
    }
    catch (EntityNotFoundException $e) {
      drupal_set_message($this->t('Error while loading encryption profile. You will need to <a href=":encrypt">assign a new encryption profile</a>, then <a href=":oauth">re-authenticate to Salesforce</a>.', [':encrypt' => Url::fromRoute('salesforce_encrypt.settings')->toString(), ':oauth' => Url::fromRoute('salesforce.authorize')->toString()]), 'error');
      return $value;
    }

    if (empty($profile)) {
      return $value;
    }
    if (!empty($value) && Unicode::strlen($value) !== 0) {
      $decrypted = $this->encryption->decrypt($value, $profile);
      return $decrypted;
    }
    return FALSE;
  }

  protected function setEncrypted($key, $value) {
    try {
      $profile = $this->getEncryptionProfile();
    }
    catch (EntityNotFoundException $e) {
      drupal_set_message($this->t('Error while loading encryption profile. You will need to <a href=":encrypt">assign a new encryption profile</a>, then <a href=":oauth">re-authenticate to Salesforce</a>.', [':encrypt' => Url::fromRoute('salesforce_encrypt.settings')->toString(), ':oauth' => Url::fromRoute('salesforce.authorize')->toString()]), 'error');
    }

    if (empty($profile)) {
      $this->state->set($key, $value);
      return $this;
    }
    $encrypted = $this->encryption->encrypt($value, $profile);
    $this->state->set($key, $encrypted);
    return $this;
  }

  /**
   * Get the access token.
   */
  public function getAccessToken() {
    return $this->getDecrypted('salesforce.access_token');
  }

  /**
   * Set the access token.
   *
   * @param string $token
   *   Access token from Salesforce.
   */
  public function setAccessToken($token) {
    return $this->setEncrypted('salesforce.access_token', $token);
  }

  /**
   * Get refresh token.
   */
  protected function getRefreshToken() {
    $token = $this->getDecrypted('salesforce.refresh_token');
    return $token;
  }

  /**
   * Set refresh token.
   *
   * @param string $token
   *   Refresh token from Salesforce.
   */
  protected function setRefreshToken($token) {
    return $this->setEncrypted('salesforce.refresh_token', $token);
  }

  /**
   *
   */
  protected function setIdentity($data) {
    try {
      $profile_id = $this->getEncryptionProfile();
    }
    catch (EntityNotFoundException $e) {
      // noop
    }
    if (!empty($profile_id) && is_array($data)) {
      $data = serialize($data);
    }
    $this->setEncrypted('salesforce.identity', $data);
    return $this;
  }

  /**
   * Return the Salesforce identity, which is stored in a variable.
   *
   * @return array
   *   Returns FALSE is no identity has been stored.
   */
  public function getIdentity() {
    $value = $this->getDecrypted('salesforce.identity');
    if (!empty($value) && !is_array($value)) {
      $value = unserialize($value);
    }
    return $value;
  }


  /**
   *
   */
  public function getConsumerKey() {
    return $this->getDecrypted('salesforce.consumer_key');
  }

  /**
   *
   */
  public function setConsumerKey($value) {
    return $this->setEncrypted('salesforce.consumer_key', $value);
  }

  /**
   *
   */
  public function getConsumerSecret() {
    return $this->getDecrypted('salesforce.consumer_secret');
  }

  /**
   *
   */
  public function setConsumerSecret($value) {
    return $this->setEncrypted('salesforce.consumer_secret', $value);
  }

}
