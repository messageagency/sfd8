<?php

namespace Drupal\salesforce_auth\Service;

use Drupal\Component\Utility\UrlHelper;
use Drupal\salesforce_auth\Consumer\JWTCredentials;
use OAuth\Common\Consumer\CredentialsInterface;
use OAuth\Common\Http\Exception\TokenResponseException;
use OAuth\Common\Http\Uri\Uri;
use Drupal\salesforce_auth\Storage\SalesforceAuthTokenStorageInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Uri\UriInterface;
use OAuth\OAuth2\Service\Exception\InvalidScopeException;
use OAuth\Common\Token\TokenInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Salesforce JWT OAuth service.
 */
class SalesforceAuthServiceJWT extends SalesforceAuthServiceBase {

  /** @var \Drupal\salesforce_auth\Consumer\JWTCredentials */
  protected $credentials;

  const SERVICE_TYPE = 'jwt';
  const LABEL = 'JWT';

  /**
   * SalesforceAuthServiceBase constructor.
   *
   * @param string $id
   * @param \Drupal\salesforce_auth\Consumer\JWTCredentials $credentials
   * @param \OAuth\Common\Http\Client\ClientInterface $httpClient
   * @param \Drupal\salesforce_auth\Storage\SalesforceAuthTokenStorageInterface $storage
   *
   * @throws \OAuth\OAuth2\Service\Exception\InvalidScopeException
   */
  public function __construct($id, JWTCredentials $credentials, ClientInterface $httpClient, SalesforceAuthTokenStorageInterface $storage) {
    parent::__construct($credentials, $httpClient, $storage, [], new Uri($credentials->getLoginUrl()));
    $this->id = $id;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $cred = new JWTCredentials($configuration['consumer_key'], $configuration['login_url'], $configuration['login_user'], $configuration['encrypt_key']);
    return new static($configuration['id'], $cred, $container->get('salesforce_auth.http_client_wrapper'), $container->get('salesforce_auth.token_storage'));
  }

  /**
   * {@inheritdoc}
   */
  public function requestAccessToken($code, $state = NULL) {
    // Initialize access token.
    $assertion = $this->generateAssertion();
    $data = [
      'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      'assertion' => $assertion,
    ];
    $response = $this->httpClient->retrieveResponse(new Uri($this->getAuthTokenUrl()), $data, ['Content-Type' => 'application/x-www-form-urlencoded']);
    $token = $this->parseAccessTokenResponse($response);
    $this->storage->storeAccessToken($this->service(), $token);

    // Initialize identity.
    $headers = [
      'Authorization' => 'OAuth ' . $token->getAccessToken(),
      'Content-type' => 'application/json',
    ];
    $data = $token->getExtraParams();
    $response = $this->httpClient->retrieveResponse(new Uri($data['id']), [], $headers);
    $identity = $this->parseIdentityResponse($response);
    $this->storage->storeIdentity($this->service(), $identity);

    return $token;
  }

  protected function parseIdentityResponse($responseBody) {
    $data = json_decode($responseBody, true);

    if (null === $data || !is_array($data)) {
      throw new TokenResponseException('Unable to parse response.');
    } elseif (isset($data['error'])) {
      throw new TokenResponseException('Error in retrieving token: "' . $data['error'] . '"');
    }
    return $data;
  }

  /**
   * Accessor to the storage adapter to be able to retrieve tokens
   *
   * @return SalesforceAuthTokenStorageInterface
   */
  public function getStorage() {
    return $this->storage;
  }

  /**
   * Refreshes an OAuth2 access token.
   *
   * @param TokenInterface $token
   *
   * @return TokenInterface $token
   *
   * @throws \OAuth\OAuth2\Service\Exception\MissingRefreshTokenException
   * @throws \OAuth\Common\Http\Exception\TokenResponseException
   */
  public function refreshAccessToken(TokenInterface $token) {
    $token = $this->requestAccessToken('');
    return $token;
  }

  protected function getAuthTokenUrl() {
    return $this->credentials->getLoginUrl() . '/services/oauth2/token';
  }

  /**
   * Returns a JWT Assertion to authenticate.
   *
   * @return string
   *   JWT Assertion.
   */
  private function generateAssertion() {
    $header = $this->generateAssertionHeader();
    $claim = $this->generateAssertionClaim();
    $header_encoded = $this->b64UrlEncode($header);
    $claim_encoded = $this->b64UrlEncode($claim);
    $encoded_string = $header_encoded . '.' . $claim_encoded;
    $key = \Drupal::service('key.repository')->getKey($this->credentials->getEncryptKeyId())->getKeyValue();
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
   * @return string
   *   The encoded claim.
   */
  private function generateAssertionClaim() {
    $claim = new \stdClass();
    $claim->iss = $this->credentials->getConsumerKey();
    $claim->sub = $this->credentials->getLoginUser();
    $claim->aud = $this->credentials->getLoginUrl();
    $claim->exp = \Drupal::time()->getCurrentTime() + 60;
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
