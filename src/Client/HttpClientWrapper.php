<?php

namespace Drupal\salesforce\Client;

use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Http\Uri\UriInterface;

class HttpClientWrapper implements ClientInterface {

  protected $httpClient;

  public function __construct(\GuzzleHttp\ClientInterface $httpClient) {
    $this->httpClient = $httpClient;
  }

  /**
   * {@inheritdoc}
   */
  public function retrieveResponse(
    UriInterface $endpoint,
    $requestBody,
    array $extraHeaders = array(),
    $method = 'POST'
  ) {
    dpm([$method, $endpoint->getAbsoluteUri(), ['headers' => $extraHeaders, 'form_params' => $requestBody]], __LINE__);
    $response = $this->httpClient->request($method, $endpoint->getAbsoluteUri(), ['headers' => $extraHeaders, 'form_params' => $requestBody]);
    return $response->getBody()->getContents();
  }

}