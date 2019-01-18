<?php

namespace Drupal\salesforce_encrypt;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\key\Entity\Key;
use Drupal\key\Entity\KeyConfigOverride;
use Drupal\key\KeyConfigOverrides;
use Drupal\salesforce\Entity\SalesforceAuthConfig;

class SalesforceEncrypt {

  final

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
    return FALSE;
    $configName = $auth->getConfigDependencyName();
    $config = $this->configFactory->get($auth->getConfigDependencyName());
    // If it's not overridden, we know it's not encrypted.
    if (!$config->hasOverrides()) {
      return FALSE;
    }
    // If it's overridden, see if it's an encrypted config.
    // @TODO write me
    $this->keyConfigOverrides->loadOverrides("$configName.provider_settings");
  }

  public function encryptAuthConfig(SalesforceAuthConfig $auth) {
    if ($this->isEncrypted($auth)) {
      throw new \Exception('Auth is already encrypted');
    }
    $configName = $auth->getConfigDependencyName();
    $config = $this->configFactory->getEditable($auth->getConfigDependencyName());

    $consumerKeyValue = $config->get('provider_settings.consumer_key');
    $consumerSecretValue = $auth->getPlugin()->getConsumerSecret();

    $consumerKeyKey = Key::create([
      'id' => "$configName:provider_settings.consumer_key",
      'label' => "$configName:provider_settings.consumer_key",
      'description' => 'Salesforce Encrypt generated consumer key.',
      'key_type' => 'authentication',
      'key_provider' => 'encrypted_config',
      'key_provider_settings' => [
        'encryption_profile' => $this->getProfileId(),
        'key_value' => $consumerKeyValue,
      ],
    ]);
    $consumerKeyKey->save();

    $override = KeyConfigOverride::create([
      'id' => "$configName.consumer_key",
      'label' => "$configName.consumer_key",
      'description' => 'Salesforce Encrypt generated consumer key override.',
      'config_type' => 'salesforce_auth',
      'config_name' => $auth->id(),
      'config_item' => 'provider_settings.consumer_key',
      'key_id' => $consumerKeyKey->id(),
    ]);
    $override->save();

    $config
      ->clear('provider_settings.consumer_key')
      ->save();
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