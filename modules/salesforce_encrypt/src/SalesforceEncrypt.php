<?php

namespace Drupal\salesforce_encrypt;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\key\KeyConfigOverrides;
use Drupal\salesforce\Entity\SalesforceAuthConfig;

class SalesforceEncrypt {



  public function __construct(EncryptionProfileManagerInterface $encryptionProfileManager, KeyConfigOverrides $keyConfigOverrides, ConfigFactoryInterface $configFactory, StateInterface $state) {
    $this->encryptionProfileManager = $encryptionProfileManager;
    $this->keyConfigOverrides = $keyConfigOverrides;
    $this->configFactory = $configFactory;
    $this->state = $state;
  }

  /**
   * TRUE if the given authconfig is overridden with encrypted values.
   *
   * @param \Drupal\salesforce\Entity\SalesforceAuthConfig $authConfig
   *   The given auth config.
   *
   * @return bool
   *   TRUE if the given authconfig is overridden with encrypted values.
   */
  public function isEncrypted(SalesforceAuthConfig $auth) {
    $config = $this->configFactory->get($auth->getConfigDependencyName());
    // If it's not overridden, we know it's not encrypted.
    if (!$config->hasOverrides()) {
      return FALSE;
    }
    // If it's overridden, see if it's an encrypted config.
    // @TODO write me
    $this->keyConfigOverrides->loadOverrides()
  }

  /**
   * Get the assigned encryption profile, or NULL.
   *
   * @return \Drupal\encrypt\EncryptionProfileInterface|NULL
   *   The assigned encryption profile, or NULL.
   */
  public function getProfile() {
    $profileId = $this->getProfileId();
    return $this->encryptionProfileManager->getEncryptionProfile($profileId);
  }

  /**
   * Get the assigned encryption profile id, or NULL.
   *
   * @return string|NULL
   *   The assigned encryption profile id, or NULL.
   */
  public function getProfileId() {
    return $this->state->get('salesforce_encrypt.profile');
  }


}