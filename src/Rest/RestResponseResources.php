<?php

namespace Drupal\salesforce\Rest;

/**
 * Class RestResponseResources.
 *
 * @package Drupal\salesforce\Rest
 */
class RestResponseResources extends RestResponse {

  /**
   * List of API endpoint paths.
   *
   * Accessible via RestResponse:__get()
   *
   * @var array
   */
  protected $resources;

  /**
   * RestResponseResources constructor.
   *
   * See https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/dome_discoveryresource.htm.
   *
   * @param \Drupal\salesforce\Rest\RestResponse $response
   *   The response.
   */
  public function __construct(RestResponse $response) {
    parent::__construct($response->response);
    foreach ($response->data as $key => $path) {
      $this->resources[$key] = $path;
    }
  }

}
