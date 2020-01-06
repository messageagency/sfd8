<?php

/**
 * @file
 * Examples.
 *
 * @noinspection ALL
 */

exit;

// @codingStandardsIgnoreStart
// Include the exception class:
use Drupal\salesforce\Rest\RestException;

// Your api path should NOT include a domain, but should include any query
// string params. The leading slash, "/", on your path tells apiCall() that
// this is a custom endpoint:
$path = '/services/apexrest/MyEndpoint?getParam1=getValue1&getParam2=getValue2';

// Create your POST body appropriately, if necessary.
// This must be an array, which will be json-encoded before POSTing.
$payload = ['postParam1' => 'postValue1', 'postParam2' => 'postValue2', 'etc...'];

$returnObject = FALSE;
// Uncomment the following line to get Drupal\salesforce\Rest\RestResponse object instead of json-decoded value:
// $returnObject = TRUE;.
// Instantiate the client so we can reference the response later if necessary:

/** @var \Drupal\salesforce\Rest\RestClientInterface $client */
$client = \Drupal::service('salesforce.client');

$method = 'POST';

try {
  // apiCall() method will pre-pend the appropriate instance URL, send request
  // with OAuth headers, and will automatically retry ONCE if SF responds with
  // status code 401.
  // $response_data is json-decoded response body.
  // (or RestResponse if $returnObject is TRUE).
  /**
 * @var mixed array | Drupal\salesforce\Rest\RestResponse **/
  $response_data = $client->apiCall($path, $payload, $method, $returnObject);
}
catch (RestException $e) {
  // RestException will be raised if:
  // - SF responds with 300+ status code, or if Response
  // - SF response body is not valid JSON
  // - SF response body is empty
  // - SF response contains an 'error' element
  // - SF response contains an 'errorCode' element.
  /**
 * @var Psr\Http\Message\ResponseInterface **/
  $response = $e->getResponse();

  // Convenience wrapper for $response->getBody()->getContents()
  /**
 * @var string **/
  $responseBody = $e->getResponseBody();

  /**
 * @var int **/
  $statusCode = $response->getStatusCode();

  // Insert exception handling here.
  // ...
}
catch (\Exception $e) {
  // Another exception may be thrown, e.g. for a network error, missing OAuth credentials, invalid params, etc.
  // see GuzzleHttp\Client.
}

// @codingStandardsIgnoreEnd

