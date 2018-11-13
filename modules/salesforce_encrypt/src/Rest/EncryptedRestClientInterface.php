<?php

namespace Drupal\salesforce_encrypt\Rest;

use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\salesforce\Rest\RestClientInterface;

/**
 * Objects, properties, and methods to communicate with the Salesforce REST API.
 *
 * @deprecated use SalesforceEncryptedAuthTokenStorage
 */
interface EncryptedRestClientInterface extends RestClientInterface {

  /**
   * Encrypts all sensitive salesforce config values.
   *
   * @param string $profile_id
   *   Id of the Encrypt Profile to use for encryption.
   *
   * @return bool
   *   TRUE if encryption was enabled or FALSE if it is already enabled
   *
   * @throws RuntimeException
   *   if Salesforce encryption profile hasn't been selected
   */
  public function enableEncryption(EncryptionProfileInterface $profile);

  /**
   * Inverse of ::enableEncryption. Decrypts all sensitive salesforce config
   * values.
   *
   * @return bool
   *   TRUE if encryption was disabled or FALSE if it is already disabled
   *
   * @throws RuntimeException
   *   if Salesforce encryption profile hasn't been selected
   */
  public function disableEncryption();

  /**
   * Returns the EncryptionProfileInterface assigned to Salesforce Encrypt, or
   * NULL if no profile is assigned.
   *
   * @throws \Drupal\salesforce\EntityNotFoundException
   *   if a profile is assigned, but cannot be loaded.
   *
   * @return \Drupal\encrypt\EncryptionProfileInterface | NULL
   */
  public function getEncryptionProfile();

  /**
   * Since we rely on a specific encryption profile, we need to respond in case
   * it gets deleted. Check to see if the  profile being deleted is the one
   * assigned for encryption; if so, decrypt our config and disable encryption.
   *
   * @param \Drupal\encrypt\EncryptionProfileInterface $profile
   */
  public function hookEncryptionProfileDelete(EncryptionProfileInterface $profile);

  /**
   * Encrypts a value using the encryption profile given by salesforce_encrypt.profile.
   *
   * @param string $value
   *   The value to encrypt.
   *
   * @return string
   *   The encrypted value.
   */
  public function encrypt($value);

  /**
   * Decrypts a value using the encryption profile given by salesforce_encrypt.profile.
   *
   * @param string $value
   *   The value to decrypt.
   *
   * @return string
   *   The decrypted value.
   */
  public function decrypt($value);

}
