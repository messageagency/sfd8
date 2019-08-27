<?php

namespace Drupal\salesforce\Tests;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Uri\UriInterface;

/**
 * Wraps Guzzle HTTP client for an OAuth ClientInterface.
 */
class TestHttpClientWrapper implements ClientInterface {

  /**
   * Guzzle HTTP Client service.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * HttpClientWrapper constructor.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   Guzzle HTTP client service, from core http_client.
   */
  public function __construct(GuzzleClientInterface $httpClient) {
    $this->httpClient = $httpClient;
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveResponse(
    UriInterface $endpoint,
    $requestBody,
    array $extraHeaders = [],
    $method = 'POST'
  ) {
    // This method is only used to Salesforce OAuth. Based on the given args,
    // return a hard-coded version of the expected response.
    if (is_array($requestBody) && array_key_exists('grant_type', $requestBody)) {
      switch ($requestBody['grant_type']) {
        case 'authorization_code':
          return file_get_contents(__DIR__ . './oauthResponse.json');

        case 'urn:ietf:params:oauth:grant-type:jwt-bearer':
          return file_get_contents(__DIR__ . './jwtAuthResponse.json');
      }
    }
    return '';
  }

}
