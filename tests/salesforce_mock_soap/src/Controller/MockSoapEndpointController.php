<?php

/**
 * @file
 * Contains \Drupal\salesforce_mock_soap\Controller\MockSoapEndpointController.
 */

namespace Drupal\salesforce_mock_soap\Controller;

use Drupal\Core\Controller\ControllerBase;

class MockSoapEndpointController extends ControllerBase {

  function endpoint() {
    $server = new SoapServer(NULL, ['uri' => $base_url . '/cvrp_mock/endpoint']);
    $server->setClass('');
    $server->handle();
  }

}