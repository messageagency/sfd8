<?php

namespace Drupal\salesforce_auth\Service;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_auth\Consumer\JWTCredentials;
use OAuth\Common\Consumer\CredentialsInterface;
use OAuth\Common\Http\Uri\Uri;
use OAuth\Common\Storage\TokenStorageInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Uri\UriInterface;
use OAuth\OAuth2\Service\Exception\InvalidScopeException;
use OAuth\OAuth2\Service\Exception\MissingRefreshTokenException;
use OAuth\Common\Token\TokenInterface;

/**
 * Salesforce JWT OAuth service.
 */
class SalesforceJWT extends SalesforceBase {

  /** @var string */
  protected $apiVersion;

  /** @var \Drupal\salesforce_auth\Consumer\JWTCredentials */
  protected $credentials;

  /**
   * @param CredentialsInterface $credentials
   * @param ClientInterface $httpClient
   * @param TokenStorageInterface $storage
   * @param array $scopes
   * @param UriInterface|null $baseApiUri
   * @param bool $stateParameterInAutUrl
   * @param string $apiVersion
   *
   * @throws InvalidScopeException
   */
  public function __construct(JWTCredentials $credentials, ClientInterface $httpClient, TokenStorageInterface $storage) {
    parent::__construct($credentials, $httpClient, $storage, [], new Uri($credentials->getLoginUrl()));
  }

  public function id() {
    return 'jwt';
  }

  public function label() {
    return 'JWT';
  }

  /**
   * {@inheritdoc}
   */
  public function requestAccessToken($code, $state = NULL) {
    $assertion = $this->generateAssertion();
    $data = UrlHelper::buildQuery([
      'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
      'assertion' => $assertion,
    ]);
    $response = $this->httpClient->retrieveResponse(new Uri($this->getAuthTokenUrl()), $data, ['Content-Type' => 'application/x-www-form-urlencoded']);
    $token = $this->parseAccessTokenResponse($response);
    $this->storage->storeAccessToken($this->service(), $token);
    return $token;
  }

  /**
   * Accessor to the storage adapter to be able to retrieve tokens
   *
   * @return TokenStorageInterface
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
   * @throws MissingRefreshTokenException
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
    $claim->iss = $this->credentials->getConsumerId();
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
