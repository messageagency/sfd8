<?php

namespace Drupal\salesforce_mock_soap\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 *
 */
class MockSoapEndpointController extends ControllerBase {

  /**
   *
   */
  public function endpoint() {
    $server = new SoapServer(NULL, ['uri' => $base_url . '/cvrp_mock/endpoint']);
    $server->setClass('');
    $server->handle();
  }

}
