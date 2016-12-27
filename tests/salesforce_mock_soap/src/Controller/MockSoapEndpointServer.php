<?php

namespace Drupal\salesforce_mock_soap\Controller;

/**
 *
 */
class MockSoapEndpointServer extends \SoapServer {

  /**
   * @param array $options
   *   A array of config values.
   * @param string $wsdl
   *   The wsdl file to use.
   */
  public function __construct($wsdl, array $options = []) {
    foreach (SforceService::$classmap as $key => $value) {
      if (!isset($options['classmap'][$key])) {
        $options['classmap'][$key] = $value;
      }
    }
    $options = array_merge([
      'features' => 1,
    ], $options);
    $this->options = $options;
    parent::__construct($wsdl, $options);
    $this->setObject($this);
  }

  /**
   * Login to the Salesforce.com SOAP Api.
   *
   * @param mixed $parameters
   *
   * @return loginResponse
   */
  public function login($parameters) {
    $userinfo = new GetUserInfoResult();
    $result = new LoginResult(FALSE, FALSE, $userinfo);
    $result->setSessionId('FakeSession');
    $result->setServerUrl($base_url . '/cvrp_mock/endpoint');
    // Return $result;
    // return new loginResponse($result);
    return new \SoapVar($result, SOAP_ENC_OBJECT, NULL, NULL, 'result');
  }

  /**
   *
   */
  private function returnSuccess($caller) {
    $result = [
      'Status' => 'Success',
    ];
    return new \SoapVar($result, SOAP_ENC_OBJECT, NULL, NULL, 'result');
  }

  /**
   *
   */
  private function returnError($caller) {
    $result = [
      'Status' => 'Error',
      'ErrorMessage' => 'Error calling mock service ' . $caller,
      'Message' => 'Error calling mock service ' . $caller,
      'ErrorName' => 'Error calling mock service ' . $caller,
    ];
    return new \SoapVar($result, SOAP_ENC_OBJECT, NULL, NULL, 'result');
  }

}
