<?php

namespace Drupal\salesforce_auth;

use Drupal\Component\Plugin\ConfigurablePluginInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use OAuth\Common\Token\TokenInterface;
use OAuth\OAuth2\Service\ServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SalesforceAuthProvider.
 *
 * @package salesforce_jwt
 */
interface SalesforceAuthProviderInterface extends ServiceInterface, PluginFormInterface, ContainerFactoryPluginInterface {

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

  public function type();

  public function refreshAccessToken(TokenInterface $token);

  public function getLoginUrl();

  /**
   * @return \OAuth\OAuth2\Token\TokenInterface
   * @throws \OAuth\Common\Storage\Exception\TokenNotFoundException
   */
  public function getAccessToken();

  public function getIdentity();

  public function hasAccessToken();

  public static function defaultConfiguration();

  public function getAuthorizationEndpoint();

  public function getAccessTokenEndpoint();

}
