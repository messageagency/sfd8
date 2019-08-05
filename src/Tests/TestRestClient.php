<?php

namespace Drupal\salesforce\Tests;

use Drupal\salesforce\Rest\RestResponse;
use Drupal\salesforce\Rest\RestResponseDescribe;
use Drupal\salesforce\Query\SelectResult;
use GuzzleHttp\Psr7\Response;
use Drupal\salesforce\Rest\RestClient;

/**
 * Test Salesforce REST client.
 *
 * @see tests/modules/salesforce_test_rest_client
 */
class TestRestClient extends RestClient {
  const AUTH_ENDPOINT_URL = 'https://example.com/fake/auth/endpoint/for/testing';

  const AUTH_TOKEN_URL = 'https://example.com/fake/token/url/for/testing';

  /**
   * {@inheritdoc}
   */
  public function isInit() {
    return TRUE;
  }

  /**
   * Short-circuit api calls.
   */
  public function apiCall($path, array $params = [], $method = 'GET', $returnObject = FALSE) {
  }

  /**
   * Hard-code a short list of objects for testing.
   */
  public function objects(array $conditions = ['updateable' => TRUE], $reset = FALSE) {
    return json_decode(file_get_contents(__DIR__ . '/objects.json'), JSON_OBJECT_AS_ARRAY);
  }

  /**
   * Hard-code the object descriptions for testing.
   */
  public function objectDescribe($name, $reset = FALSE) {
    $contents = file_get_contents(__DIR__ . '/objectDescribe.json');
    return new RestResponseDescribe(new RestResponse(new Response(200, ['Content-Type' => 'application/json;charset=UTF-8'], $contents)));
  }

  /**
   * Hard-code record types for testing.
   */
  public function getRecordTypes($name = NULL, $reset = FALSE) {
    $json = json_decode(file_get_contents(__DIR__ . '/recordTypes.json'), JSON_OBJECT_AS_ARRAY);
    $result = new SelectResult($json);
    $record_types = [];
    foreach ($result->records() as $rt) {
      $record_types[$rt->field('SobjectType')][$rt->field('DeveloperName')] = $rt;
    }
    if ($name != NULL) {
      if (!isset($record_types[$name])) {
        throw new \Exception("No record types for $name");
      }
      return $record_types[$name];
    }
    return $record_types;
  }

  /**
   * Helper callback for OAuth handshake, and refreshToken()
   *
   * @param \GuzzleHttp\Psr7\Response $response
   *   Response object from refreshToken or authToken endpoints.
   *
   * @see SalesforceController::oauthCallback()
   * @see self::refreshToken()
   */
  public function handleAuthResponse(Response $response) {
  }

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
    return (object) ['resources' => []];
  }

}
