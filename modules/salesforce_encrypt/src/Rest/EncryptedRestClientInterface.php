<?php

namespace Drupal\salesforce_encrypt\Rest;

use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\salesforce\Rest\RestClientInterface;

/**
 * Objects, properties, and methods to communicate with the Salesforce REST API.
 */
interface EncryptedRestClientInterface extends RestClientInterface {

  /**
   * Encrypts all sensitive salesforce config values.
   *
   * @param \Drupal\encrypt\EncryptionProfileInterface $profile
   *   Id of the Encrypt Profile to use for encryption.
   *
   * @return bool
   *   TRUE if encryption was enabled or FALSE if it is already enabled
   *
   * @throws RuntimeException
   *   If Salesforce encryption profile hasn't been selected.
   */
  public function enableEncryption(EncryptionProfileInterface $profile);

  /**
   * Decrypt and re-save sensitive salesforce config values.
   *
   * Inverse of ::enableEncryption.
   *
   * @return bool
   *   TRUE if encryption was disabled or FALSE if it is already disabled
   *
   * @throws RuntimeException
   *   If Salesforce encryption profile hasn't been selected.
   */
  public function disableEncryption();

  /**
   * Returns the EncryptionProfileInterface assigned to Salesforce Encrypt.
   *
   * @return \Drupal\encrypt\EncryptionProfileInterface|null
   *   The assigned profile, or null if none has been assigned.
   *
   * @throws \Drupal\salesforce\EntityNotFoundException
   *   If a profile is assigned, but cannot be loaded.
   */
  public function getEncryptionProfile();

  /**
   * If the given profile is our active one, disable encryption.
   *
   * Since we rely on a specific encryption profile, we need to respond in case
   * it gets deleted. Check to see if the profile being deleted is the one
   * assigned for encryption; if so, decrypt our config and disable encryption.
   *
   * @param \Drupal\encrypt\EncryptionProfileInterface $profile
   *   The encryption profile being deleted.
   */
  public function hookEncryptionProfileDelete(EncryptionProfileInterface $profile);

  /**
   * Encrypts a value using the active encryption profile, or return plaintext.
   *
   * @param string $value
   *   The value to encrypt.
   *
   * @return string
   *   The encrypted value, or plaintext if no active profile.
   */
  public function encrypt($value);

  /**
   * Decrypts a value using active encryption profile, or return the same value.
   *
   * @param string $value
   *   The value to decrypt.
   *
   * @return string
   *   The decrypted value, or the unchanged value if no active profile.
   */
  public function decrypt($value);

}
