<?php

namespace Drupal\salesforce_auth;

/**
 * Represents an auth token (with lookups to correlated project config).
 */
interface AuthTokenInterface {

  /**
   * Get the project label from correlated config.
   */
  public function label();

  /**
   * Project id getter.
   */
  public function getAuthConfigId();

  /**
   * Access token getter.
   */
  public function getAccessToken();

  /**
   * Refresh token getter.
   */
  public function getRefreshToken();

  /**
   * Scope getter.
   */
  public function getScope();

  /**
   * Instance URL getter.
   */
  public function getInstanceUrl();

  /**
   * Endpoint getter.
   */
  public function getEndpoint($class_name);

}
