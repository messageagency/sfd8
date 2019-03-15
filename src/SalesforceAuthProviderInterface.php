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
  const LATEST_API_VERSION = '44.0';

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
   * Return the credentials configured for this auth provider instance.
   *
   * Credentials contain consumer key, login url, secret, etc.
   *
   * @return \Drupal\salesforce\Consumer\SalesforceCredentialsInterface
   *   The credentials.
   */
  public function getCredentials();

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
   * Clear the access token for this auth provider plugin.
   *
   * @return $this
   */
  public function revokeAccessToken();

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
   * Get the globally configured API version to use.
   *
   * @return string
   *   The string name of the API version.
   */
  public function getApiVersion();

  /**
   * API Url for this plugin.
   *
   * @param string $api_type
   *   (optional) Which API for which to retrieve URL, defaults to "rest".
   *
   * @return string
   *   The URL.
   */
  public function getApiEndpoint($api_type = 'rest');

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

  /**
   * The auth provider service.
   *
   * @return \Drupal\salesforce\SalesforceAuthProviderInterface
   *   The auth provider service.
   */
  public function service();

}
