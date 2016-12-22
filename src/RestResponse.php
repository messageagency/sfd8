<?php

namespace Drupal\salesforce;

use GuzzleHttp\Psr7\Response;
use Drupal\Component\Serialization\Json;

class RestResponse extends Response {

  protected $response;
  protected $data;

  /**
   * {@inheritdoc}
   * @throws RestException if body cannot be json-decoded
   */
  function __construct(Response $response) {
    $this->response = $response;
    parent::__construct($response->getStatusCode(), $response->getHeaders(), $response->getBody(), $response->getProtocolVersion(), $response->getReasonPhrase());
    $this->handleJsonResponse();
  }

  /**
   * Get the orignal response
   *
   * @return GuzzleHttp\Psr7\Response;
   */
  public function response() {
    return $this->response;
  }

  /**
   * Get the json-decoded data object from the response body
   *
   * @return stdObject
   */
  public function data() {
    return $this->data;
  }

  /**
   * Helper function to eliminate repetitive json parsing.
   *
   * @return $this
   * @throws Drupal\salesforce\RestException
   */
  private function handleJsonResponse() {
    $this->data = '';
    $response_body = $this->getBody()->getContents();
    if (empty($response_body)) {
      return;
    }

    // Allow any exceptions here to bubble up:
    try {
      $data = Json::decode($response_body);
    }
    catch (UnexpectedValueException $e) {
      throw new RestException($this, $e->getMessage(), $e->getCode(), $e);
    }

    if (empty($data)) {
      throw new RestException($this, t('Invalid response'));
    }

    if (!empty($data['error'])) {
      throw new RestException($this, $data['error']);
    }

    if (!empty($data[0]) && count($data) == 1) {
      $data = $data[0];
    }

    if (!empty($data['error'])) {
      throw new RestException($this, $data['error']);
    }

    if (!empty($data['errorCode'])) {
      throw new RestException($this, $data['errorCode']);
    }
    $this->data = $data;
    return $this;
  }

}