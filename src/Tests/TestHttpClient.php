<?php

namespace Drupal\salesforce\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

/**
 * Empty http client.
 *
 * @see tests/modules/salesforce_test_rest_client
 */
class TestHttpClient extends Client {

  /**
   * We need to override the post() method in order to fake our OAuth process.
   */
  public function post($url, $headers) {
    return new Response();
  }

}
