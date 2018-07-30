<?php

namespace Drupal\salesforce_auth;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class SalesforceAuth for salesforce_auth service.
 *
 * @package Drupal\salesforce_auth
 */
class SalesforceAuth {

  protected $providers;
  protected $config;
  protected $storage;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $etm;

  /**
   * Salesforce Auth storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $authStorage;

  public function __construct(EntityTypeManagerInterface $etm) {
    $this->etm = $etm;
    $this->authStorage = $etm->getStorage('salesforce_auth');
  }

  public function addHandler(AuthProviderInterface $provider) {
    $this->providers[$provider->id()] = $provider;
  }

  public function getProviders() {
    return $this->authStorage->loadMultiple();
  }

  public function hasProviders() {
    return $this->authStorage->hasData();
  }

  /**
   * Get the active provider, or null if it has not been assigned.
   *
   * @return \Drupal\salesforce_auth\AuthProviderInterface|null
   */
  public function getProvider() {
    $provider_id = $this->config()->get('provider');
    if (empty($provider_id)) {
      return NULL;
    }
    return $this->authStorage->load($provider_id);
  }

  /**
   * Get the active config, or null if it has not been assigned.
   *
   * @return \Drupal\salesforce_auth\AuthTokenInterface|null
   */
  public function getConfig() {
    if (!$provider = $this->getProvider()) {
      return NULL;
    }
    $config_id = $this->config()->get('config');
    if (empty($config_id)) {
      return NULL;
    }
    return $provider->getConfig($config_id);
  }

  /**
   * Get the active token, or null if it has not been assigned.
   *
   * @return \Drupal\salesforce_auth\AuthTokenInterface|null
   */
  public function getToken() {
    if (!$provider = $this->getProvider()) {
      return NULL;
    }
    $config_id = $this->config()->get('config');
    if (empty($config_id)) {
      return NULL;
    }
    return $provider->getToken($config_id);
  }

  public function config() {
    if (!$this->config) {
      $this->config = \Drupal::config('salesforce_auth.settings');
    }
    return $this->config;
  }

}