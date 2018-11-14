<?php

namespace Drupal\salesforce_encrypt;

use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\salesforce\SalesforceOAuthPluginInterface;

/**
 * Encrypted oauth provider interface.
 */
interface SalesforceEncryptedOAuthPluginInterface extends SalesforceOAuthPluginInterface {

  /**
   * Callback for hook_encryption_profile_predelete().
   *
   * @param \Drupal\encrypt\EncryptionProfileInterface $profile
   *   The encryption profile being deleted.
   */
  public function hookEncryptionProfileDelete(EncryptionProfileInterface $profile);

  /**
   * Get the encryption profile assigned to this auth plugin.
   *
   * @return \Drupal\encrypt\EncryptionProfileInterface|null
   *   Profile.
   */
  public function encryptionProfile();

  /**
   * Decrypt a given value, using the assigned encryption profile.
   *
   * @param string $value
   *   The encrypted value.
   *
   * @return string
   *   The plain text value.
   *
   * @throws \Drupal\encrypt\Exception\EncryptException
   *   On decryption error.
   */
  public function decrypt($value);

  /**
   * Encrypt a value, using the assigned encryption profile.
   *
   * @param string $value
   *   The plain text value.
   *
   * @return string
   *   The encrypted value.
   *
   * @throws \Drupal\encrypt\Exception\EncryptException
   *   On error.
   */
  public function encrypt($value);

}
