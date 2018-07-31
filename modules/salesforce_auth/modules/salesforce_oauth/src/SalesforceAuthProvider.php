<?php

namespace Drupal\salesforce_oauth;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\salesforce\Rest\RestResponse;
use Drupal\salesforce_auth\SalesforceAuthProviderInterface;
use Drupal\salesforce_auth\AuthTokenInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\State\StateInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\salesforce\Event\SalesforceErrorEvent;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce_oauth\Entity\OAuthConfig;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GUzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

  /**
   * Class SalesforceAuthProvider.
   *
   * @package salesforce_oauth
   */
class SalesforceAuthProvider implements SalesforceAuthProviderInterface {

  /**
   * The HTTP cclient.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * State.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Time.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * Key repository.
   *
   * @var \Drupal\key\KeyRepository
   */
  protected $key;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * SalesforceAuthProvider constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The Guzzle http client.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The date time service.
   * @param \Drupal\key\KeyRepositoryInterface $key
   *   The key repository.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  public function __construct(Client $http_client, StateInterface $state, TimeInterface $time, KeyRepositoryInterface $key, EventDispatcherInterface $eventDispatcher) {
    $this->httpClient = $http_client;
    $this->state = $state;
    $this->time = $time;
    $this->key = $key;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return 'oauth';
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return 'OAuth';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigs() {
    return OAuthConfig::loadMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig($id) {
    return OAuthConfig::load($id);
  }

  /**
   * Factory method to generate an AuthToken stub for a given oauth config id.
   *
   * Configures the AuthToken argument, given project name config.
   *
   * @param string $id
   *   Id of a OAuthConfig record.
   *
   * @return \Drupal\salesforce_auth\AuthTokenInterface
   *   The fully initialized auth token.
   */
  public function getToken($id) {
    $data = $this->state->get("salesforce_oauth.auth_data.$id") ?: [];
    return new AuthToken($data + ['authConfigId' => $id] + ['identity' => $this->state->get("salesforce_oauth.identity.$id")]);
  }

  /**
   * {@inheritdoc}
   */
  public function refreshToken(AuthTokenInterface $token) {
    $refresh_token = $token->getRefreshToken();
    if (empty($refresh_token)) {
      throw new \Exception(t('There is no refresh token.'));
    }
    $configId = $token->getAuthConfigId();
    if (empty($configId)) {
      throw new \Exception(t('No auth config for token.'));
    }
    $config = OAuthConfig::load($configId);
    if (empty($config)) {
      throw new \Exception(t('No auth config ' . $configId));
    }
    $data = UrlHelper::buildQuery([
      'grant_type' => 'refresh_token',
      'refresh_token' => urldecode($refresh_token),
      'client_id' => $config->getConsumerKey(),
      'client_secret' => $config->getConsumerSecret(),
    ]);

    $url = $config->getAuthTokenUrl();
    $headers = [
      // This is an undocumented requirement on Salesforce's end.
      'Content-Type' => 'application/x-www-form-urlencoded',
    ];
    $response = $this->httpClient->post($url, ['form_params' => $data, 'headers' => $headers]);
    $this->handleAuthResponse($response, $token);
    return $this;
  }

  /**
   * Handle an auth response - either an oauth request or a token refresh.
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The oauth response.
   *
   * @throws \Exception
   */
  public function handleAuthResponse(ResponseInterface $response, AuthTokenInterface $token) {
    if ($response->getStatusCode() != 200) {
      throw new \Exception($response->getReasonPhrase(), $response->getStatusCode());
    }

    $data = (new RestResponse($response))->data;

    $this
      ->storeAuthData($data, $token)
      ->initializeIdentity($data, $token);
  }

  /**
   * Revoke authorization for a given oauth config.
   */
  public function revokeAuthorization(OAuthConfig $oauthConfig) {
    $this->state->deleteMultiple([
      "salesforce_oauth.auth_data." . $oauthConfig->id(),
      "salesforce_oauth.identity."  . $oauthConfig->id(),
    ]);
    return $this;
  }

  /**
   * Set the access token.
   *
   * @param array $data
   *   Auth response data from salesforce.
   * @param string $configId.
   *   OAuthConfig ID for which to store auth data.
   *
   * @return $this
   */
  protected function storeAuthData(array $data, AuthTokenInterface $token) {
    $this->state->set("salesforce_oauth.auth_data." . $token->getAuthConfigId(), $data);
    return $this;
  }

  /**
   * Retrieve and store the Salesforce identity given an ID url.
   *
   * @param array $data
   *   Auth response data.
   * @param string $access_token
   *   OAuth Config ID.
   *
   * @return $this
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\RequestException
   */
  protected function initializeIdentity(array $data, AuthTokenInterface $token) {
    $headers = [
      'Authorization' => 'OAuth ' . $data['access_token'],
      'Content-type' => 'application/json',
    ];
    $response = $this->httpClient->get($data['id'], ['headers' => $headers]);

    if ($response->getStatusCode() != 200) {
      throw new \Exception(t('Unable to access identity service.'), $response->getStatusCode());
    }
    $data = (new RestResponse($response))->data;
    $this->state->set("salesforce_oauth.identity." . $token->getAuthConfigId(), $data);
    return $this;
  }


}