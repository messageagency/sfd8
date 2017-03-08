<?php

namespace Drupal\salesforce_encrypt\Rest;

use Drupal\salesforce\Rest\RestClientBaseInterface;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;

/**
 * Objects, properties, and methods to communicate with the Salesforce REST API.
 */
interface EncryptedRestClientInterface extends RestClientBaseInterface {

  /**
   * Constructor which initializes the consumer.
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
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, StateInterface $state, CacheBackendInterface $cache, EncryptServiceInterface $encryption, EncryptionProfileManagerInterface $encryptionProfileManager, LockBackendInterface $lock);

  /**
   * Encrypts all sensitive salesforce config values.
   *
   * @param string $profile_id Id of the Encrypt Profile to use for encryption
   *
   * @return TRUE if encryption was enabled or FALSE if it is already enabled
   * @throws RuntimeException if Salesforce encryption profile hasn't been
   *   selected
   */
  public function enableEncryption(EncryptionProfileInterface $profile);

  /**
   * Inverse of ::enableEncryption. Decrypts all sensitive salesforce config
   * values.
   *
   * @return TRUE if encryption was disabled or FALSE if it is already disabled
   * @throws RuntimeException if Salesforce encryption profile hasn't been
   *   selected
   */
  public function disableEncryption();

  /**
   * Returns the EncryptionProfileInterface assigned to Salesforce Encrypt, or
   * NULL if no profile is assigned.
   *
   * @throws Drupal\salesforce\EntityNotFoundException if a profile is
   *   assigned, but cannot be loaded.
   * @return EncryptionProfileInterface | NULL
   */
  public function getEncryptionProfile();

  /**
   * Since we rely on a specific encryption profile, we need to respond in case
   * it gets deleted. Check to see if the  profile being deleted is the one
   * assigned for encryption; if so, decrypt our config and disable encryption.
   *
   * @param EncryptionProfileInterface $profile
   */
  public function hookEncryptionProfileDelete(EncryptionProfileInterface $profile);

}
