<?php

namespace Drupal\salesforce;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use OAuth\Common\Token\TokenInterface;
use OAuth\OAuth2\Service\ServiceInterface;

/**
 * Class SalesforceAuthProvider.
 */
interface SalesforceAuthProviderInterface extends ServiceInterface, PluginFormInterface, ContainerFactoryPluginInterface, PluginInspectionInterface {

  const AUTH_TOKEN_PATH = '/services/oauth2/token';
  const AUTH_ENDPOINT_PATH = '/services/oauth2/authorize';
  const SOAP_CLASS_PATH = '/services/Soap/class/';

  /**
   * Id of this service.
   *
   * @return string
   *   Id of this service.
   */
  public function id();

  /**
   * Label of this service.
   *
   * @return string
   *   Id of this service.
   */
  public function label();

  /**
   * Auth type id for this service, e.g. oauth, jwt, etc.
   *
   * @return string
   *   Provider type for this auth provider.
   */
  public function type();

  /**
   * Perform a refresh of the given token.
   *
   * @param \OAuth\Common\Token\TokenInterface $token
   *   The token.
   *
   * @return \OAuth\Common\Token\TokenInterface
   *   The refreshed token.
   *
   * @throws \OAuth\OAuth2\Service\Exception\MissingRefreshTokenException
   *   Comment.
   */
  public function refreshAccessToken(TokenInterface $token);

  /**
   * Login URL, e.g. https://login.salesforce.com, for this plugin.
   *
   * @return string
   *   Login URL.
   */
  public function getLoginUrl();

  /**
   * Consumer key for the connected OAuth app.
   *
   * @return string
   *   Consumer key.
   */
  public function getConsumerKey();

  /**
   * Consumer secret for the connected OAuth app.
   *
   * @return string
   *   Consumer secret.
   */
  public function getConsumerSecret();

  /**
   * Access token for this plugin.
   *
   * @return \OAuth\OAuth2\Token\TokenInterface
   *   The Token.
   *
   * @throws \OAuth\Common\Storage\Exception\TokenNotFoundException
   */
  public function getAccessToken();

  /**
   * Identify for this connection.
   *
   * @return array
   *   Identity for this connection.
   */
  public function getIdentity();

  /**
   * TRUE if the connection has a token, regardless of validity.
   *
   * @return bool
   *   TRUE if the connection has a token, regardless of validity.
   */
  public function hasAccessToken();

  /**
   * Default configuration for this plugin type.
   *
   * @return array
   *   Default configuration.
   */
  public static function defaultConfiguration();

  /**
   * Authorization URL for this plugin type.
   *
   * @return string
   *   Authorization URL for this plugin type.
   */
  public function getAuthorizationEndpoint();

  /**
   * Access token URL for this plugin type.
   *
   * @return string
   *   Access token URL for this plugin type.
   */
  public function getAccessTokenEndpoint();

  /**
   * Instance URL for this connection.
   *
   * @return string
   *   Instance URL for this connection.
   *
   * @throws \OAuth\Common\Storage\Exception\TokenNotFoundException
   */
  public function getInstanceUrl();

  /**
   * Callback for configuration form after saving config entity.
   *
   * @param array $form
   *   The configuration form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function save(array $form, FormStateInterface $form_state);

}
