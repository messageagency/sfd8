<?php

namespace Drupal\salesforce\Client;

use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Uri\UriInterface;

/**
 * Wraps Guzzle HTTP client for an OAuth ClientInterface.
 */
class HttpClientWrapper implements ClientInterface {

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
    $response = $this->httpClient->request($method, $endpoint->getAbsoluteUri(), ['headers' => $extraHeaders, 'form_params' => $requestBody]);
    return $response->getBody()->getContents();
  }

}
