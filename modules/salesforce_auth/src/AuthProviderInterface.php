<?php

namespace Drupal\salesforce_auth;

use Drupal\salesforce_jwt\Entity\JWTAuthConfig;
use OAuth\Common\Token\TokenInterface;
use OAuth\OAuth2\Service\ServiceInterface;

/**
 * Class AuthProvider.
 *
 * @package salesforce_jwt
 */
interface AuthProviderInterface extends ServiceInterface {

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

  public function refreshAccessToken(TokenInterface $token);

}
