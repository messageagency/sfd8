<?php

namespace Drupal\salesforce\Rest;

class RestResponse_Upsert extends RestResponse {

  protected $resources;

  /**
   * See https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/dome_discoveryresource.htm
   *
   * @param RestResponse $response 
   */
  function __construct(RestResponse $response) {
    parent::__construct($response->response);
    foreach ($response->data() as $key => $path) {
      $this->resources[$key] = $path;
    }
  }

  /**
   * Return a list of available resources for the configured API version.
   *
   * @return array
   *   Associative array keyed by name with a URI value.
   */
  function getResources() {
    return $this->resources;
  }

  /**
   * Given a resource name, return its endpoint path
   *
   * @param string $key 
   */
  function getResource($key) {
    return $this->resources[$key];
  }

}
