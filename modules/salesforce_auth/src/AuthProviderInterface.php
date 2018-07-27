<?php

namespace Drupal\salesforce_auth;

use Drupal\salesforce_jwt\Entity\JWTAuthConfig;

/**
 * Class AuthProvider.
 *
 * @package salesforce_jwt
 */
interface AuthProviderInterface {

  const AUTH_TOKEN_PATH = '/services/oauth2/token';
  const AUTH_ENDPOINT_PATH = '/services/oauth2/authorize';
  const SOAP_CLASS_PATH = '/services/Soap/class/';

  /**
   * Return an id for this service.
   *
   * @return string
   *   Id of this service.
   */
  public function id();

  /**
   * Return an id for this service.
   *
   * @return string
   *   Id of this service.
   */
  public function label();

  /**
   * Get an array of configs for this provider.
   *
   * @return \Drupal\salesforce_auth\Entity\AuthConfigInterface[]
   *   The configs for this provider.
   */
  public function getConfigs();

  /**
   * Get a single config, given its id.
   * @param string $id
   *   The auth config id.
   *
   * @return \Drupal\salesforce_auth\AuthTokenInterface|null
   */
  public function getConfig($id);

  /**
   * Factory method to generate an AuthToken stub for a given credential.
   *
   * @param string $id
   *   Id of a auth config record, if applicable.
   *
   * @return \Drupal\salesforce_auth\AuthTokenInterface
   *   The fully initialized auth token.
   */
  public function getToken($id);

  /**
   * Refresh the given access token from Salesforce.
   *
   * @param \Drupal\salesforce_auth\AuthTokenInterface $token
   *   The existing token, or token stub.
   *
   * @return \Drupal\salesforce_auth\AuthTokenInterface
   *   The fresh token.
   *
   * @throws \Exception
   *   On any network or other connectivity issue.
   */
  public function refreshToken(AuthTokenInterface $token);

}
