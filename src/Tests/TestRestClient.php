<?php

namespace Drupal\salesforce\Tests;

use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SObject;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\SelectQueryResult;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\salesforce\Rest\RestClient;

/**
 * @see tests/modules/salesforce_test_rest_client
 */
class TestRestClient extends RestClient {

  const AUTH_ENDPOINT_URL = 'https://example.com/fake/auth/endpoint/for/testing';

  const AUTH_TOKEN_URL = 'https://example.com/fake/token/url/for/testing';

  /**
   * Always return TRUE for test client
   */
  public function isAuthorized() {
    return TRUE;
  }

  /**
   * Short-circuit api calls
   */
  public function apiCall($path, array $params = [], $method = 'GET', $returnObject = FALSE) { }

  /**
   * Short-circuit config-helper methods for our purposes.
   */
  // public function getConsumerKey() { }
  // public function setConsumerKey($value) { }
  // public function getConsumerSecret() { }
  // public function setConsumerSecret($value) { }
  // public function getLoginUrl() { }
  // public function setLoginUrl($value) { }
  // public function getAccessToken() { }
  // public function setAccessToken($token) { }

  /**
   * Helper callback for OAuth handshake, and refreshToken()
   *
   * @param GuzzleHttp\Psr7\Response $response
   *   Response object from refreshToken or authToken endpoints.
   *
   * @see SalesforceController::oauthCallback()
   * @see self::refreshToken()
   */
  public function handleAuthResponse(Response $response) { }

  /**
   * Get the fake OAuth endpoint.
   *
   * @return string
   *   REST OAuth Login URL.
   */
  public function getAuthEndpointUrl() {
    return self::AUTH_ENDPOINT_URL;
  }

  /**
   * Get the fake Auth token endpoint.
   *
   * @return string
   *   REST OAuth Token URL.
   */
  public function getAuthTokenUrl() { 
    return self::AUTH_TOKEN_URL;
  }

  /**
   * Prevent an API call out here.
   */
  public function listResources() {
    return (object)['resources' => []];
  }

}