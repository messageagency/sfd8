<?php

namespace Drupal\salesforce_auth;

/**
 * Class SalesforceAuth for salesforce_auth service.
 *
 * @package Drupal\salesforce_auth
 */
class SalesforceAuth {

  protected $providers;
  protected $config;

  public function addHandler(AuthProviderInterface $provider) {
    $this->providers[$provider->id()] = $provider;
  }

  public function getProviders() {
    return $this->providers;
  }

  public function hasProviders() {
    return !empty($this->providers);
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
    return !empty($this->providers[$provider_id]) ? $this->providers[$provider_id] : NULL;
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