<?php

namespace Drupal\salesforce_jwt;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\State\StateInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\salesforce\Event\SalesforceErrorEvent;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Rest\RestResponse;
use Drupal\salesforce_auth\SalesforceAuthProviderInterface;
use Drupal\salesforce_auth\AuthTokenInterface;
use Drupal\salesforce_jwt\Entity\JWTAuthConfig;
use GuzzleHttp\ClientInterface;
use GUzzleHttp\Exception\GuzzleException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class SalesforceAuthProvider.
 *
 * @package salesforce_jwt
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
  public function __construct(ClientInterface $http_client, StateInterface $state, TimeInterface $time, KeyRepositoryInterface $key, EventDispatcherInterface $eventDispatcher) {
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
    return 'jwt';
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return 'JWT Auth';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigs() {
    return JWTAuthConfig::loadMultiple();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig($id) {
    return JWTAuthConfig::load($id);
  }

  /**
   * Factory method to generate an AuthToken stub for a given project.
   *
   * Configures the AuthToken argument, given project name config.
   *
   * @param string $id
   *   Id of a JWTAuthConfig record.
   *
   * @return \Drupal\salesforce_auth\AuthTokenInterface
   *   The fully initialized auth token.
   */
  public function getToken($id) {
    $token = new AuthToken($id);
    $key = $this->state->get("salesforce_jwt_auth.$id");
    if (!empty($key)) {
      $token->setToken($key);
      $token->setIdentity($this->state->get("salesforce_jwt_identity.$id"));
      return $token;
    }

    return $this->refreshToken($token);
  }

  /**
   * Refresh the given access token from Salesforce.
   *
   * @param \Drupal\salesforce_auth\AuthTokenInterface $token
   *   The existing token, or token stub.
   *
   * @return \Drupal\salesforce_auth\AuthTokenInterface
   *   If the token was refreshed, $token->refreshed will be TRUE.
   *   Otherwise, the token was not refreshed for some reason.
   */
  public function refreshToken(AuthTokenInterface $token) {
    $id = $token->getAuthConfigId();
    if (!$id) {
      return $token;
    }
    $config = JWTAuthConfig::load($id);
    if (!$config) {
      // If we couldn't find the config, Drupal dies here.
      // Don't throw an exception, or Drupal will WSOD.
      return $token;
    }
    $response = $this->getJwtToken($config);
    $this->state->set("salesforce_jwt_auth.$id", $response);
    $token->setToken($response);
    $token->refreshed = TRUE;
    $this->initializeIdentity(get_object_vars($response), $token);
    return $token;
  }

  /**
   * Revoke authorization for a given oauth config.
   */
  public function revokeAuthorization(JWTAuthConfig $oauthConfig) {
    $this->state->deleteMultiple([
      "salesforce_jwt_auth." . $oauthConfig->id(),
      "salesforce_jwt_identity."  . $oauthConfig->id(),
    ]);
    return $this;
  }

  /**
   * Gets token from OAuth endpoint.
   *
   * @param string $id
   *   Id of a JWTAuthConfig record.
   *
   * @return bool|\stdClass
   *   Token object on success, FALSE on failure.
   */
  public function getJwtToken(JWTAuthConfig $authParams) {
    $assertion = $this->generateAssertion($authParams);

    $data = UrlHelper::buildQuery([
      'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      'assertion' => $assertion,
    ]);

    try {
      $response = $this->httpClient->request('POST', $authParams->getAuthTokenUrl(), [
        'headers' => [
          'Content-Type' => 'application/x-www-form-urlencoded',
        ],
        'body' => $data,
      ]);
    }
    catch (GuzzleException $e) {
      if (\Drupal::service('module_handler')->moduleExists('devel')) {
        dpm($e);
      }
      $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e));
    }

    if (isset($response)) {
      $token = json_decode($response->getBody()->getContents());
      if (json_last_error() == JSON_ERROR_NONE) {
        return $token;
      }
    }

    return FALSE;
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
    $this->state->set("salesforce_jwt_identity." . $token->getAuthConfigId(), $data);
    return $this;
  }

  /**
   * Returns a JWT Assertion to authenticate.
   *
   * @param \Drupal\salesforce_jwt\Entity\JWTAuthConfig $authParams
   *   AuthParam object.
   *
   * @return string
   *   JWT Assertion.
   */
  private function generateAssertion(JWTAuthConfig $authParams) {

    $header = $this->generateAssertionHeader();
    $claim = $this->generateAssertionClaim($authParams);

    $header_encoded = $this->b64UrlEncode($header);
    $claim_encoded = $this->b64UrlEncode($claim);
    $encoded_string = $header_encoded . '.' . $claim_encoded;

    $key = $this->key->getKey($authParams->getEncryptKey())->getKeyValue();

    openssl_sign($encoded_string, $signed, $key, 'sha256WithRSAEncryption');

    $signed_encoded = $this->b64UrlEncode($signed);

    $assertion = $encoded_string . '.' . $signed_encoded;

    return $assertion;
  }

  /**
   * Returns a JSON encoded JWT Header.
   *
   * @return string
   *   The encoded header.
   */
  private function generateAssertionHeader() {
    $header = new \stdClass();
    $header->alg = 'RS256';

    return json_encode($header);
  }

  /**
   * Returns a JSON encoded JWT Claim.
   *
   * @param \Drupal\salesforce_jwt\Entity\JWTAuthConfig $authParams
   *   AuthParams object.
   *
   * @return string
   *   The encoded claim.
   */
  private function generateAssertionClaim(JWTAuthConfig $authParams) {
    $claim = new \stdClass();
    $claim->iss = $authParams->getConsumerKey();
    $claim->sub = $authParams->getLoginUser();
    $claim->aud = $authParams->getLoginUrl();
    $claim->exp = $this->time->getCurrentTime() + 60;

    return json_encode($claim);
  }

  /**
   * Base 64 URL Safe Encoding.
   *
   * @param string $data
   *   String to encode.
   *
   * @return string
   *   Encoded string.
   */
  private function b64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
  }

}
