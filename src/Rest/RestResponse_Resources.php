<?php

namespace Drupal\salesforce\Rest;

class RestResponse_Resources extends RestResponse {

  /**
   * List of API endpoint paths. Accessible via RestResponse:__get()
   *
   * @var array
   */
  protected $resources;

  /**
   * See https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/dome_discoveryresource.htm
   *
   * @param RestResponse $response 
   */
  function __construct(RestResponse $response) {
    parent::__construct($response->response);
    foreach ($response->data as $key => $path) {
      $this->resources[$key] = $path;
    }
  }
}
