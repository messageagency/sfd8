<?php

/**
 * @file
 * Contains \Drupal\salesforce\RestClient.
 */

namespace Drupal\salesforce;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\salesforce\SelectQuery;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Serialization\Json;

/**
 * Objects, properties, and methods to communicate with the Salesforce REST API.
 */
class RestClient {

  public $response;
  protected $httpClient;
  protected $configFactory;
  protected $urlGenerator;
  private $config;
  private $configEditable;
  private $state;

  /**
   * Constructor which initializes the consumer.
   * @param \Drupal\Core\Http\Client $http_client
   *   The config factory
   * @param \Guzzle\Http\ClientInterface $http_client
   *   The config factory
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, UrlGeneratorInterface $url_generator, StateInterface $state) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->urlGenerator = $url_generator;
    $this->config = $this->configFactory->get('salesforce.settings');
    $this->configEditable = $this->configFactory->getEditable('salesforce.settings');
    $this->state = $state;
  }

  /**
   * Determine if this SF instance is fully configured.
   *
   * @TODO: Consider making a test API call.
   */
  public function isAuthorized() {
    return $this->getConsumerKey() && $this->getConsumerSecret() && $this->getRefreshToken();
  }

  /**
   * Make a call to the Salesforce REST API.
   *
   * @param string $path
   *   Path to resource.
   * @param array $params
   *   Parameters to provide.
   * @param string $method
   *   Method to initiate the call, such as GET or POST.  Defaults to GET.
   *
   * @return mixed
   *   The requested response.
   *
   * @throws Exception
   */
  public function apiCall($path, array $params = [], $method = 'GET') {
    if (!$this->getAccessToken()) {
      $this->refreshToken();
    }

    try {
      $this->response = $this->apiHttpRequest($path, $params, $method);
    }
    catch (RequestException $e) {
      // A RequestException gets thrown if the response has any error status.
      $this->response = $e->getResponse();
    }

    if (!is_object($this->response)) {
      throw new Exception('Unknown error occurred during API call');
    }

    switch ($this->response->getStatusCode()) {
      case 401:
        // The session ID or OAuth token used has expired or is invalid: refresh
        // token. If refreshToken() throws an exception, or if apiHttpRequest()
        // throws anything but a RequestException, let it bubble up.
        $this->refreshToken();
        try {
          $this->response = $this->apiHttpRequest($path, $params, $method);
        }
        catch (RequestException $e) {
          $this->response = $e->getResponse();
          throw new Exception($this->response->getReasonPhrase(), $this->response->getStatusCode());
        }
        break;
      case 200:
      case 201:
      case 204:
        // All clear.
        break;

      default:
        // We have problem and no specific Salesforce error provided.
        if (empty($this->response)) {
          throw new Exception('Unknown error occurred during API call');
        }
    }

    // Parse a json response, if body is not empty. Sometimes an empty body is valid, e.g. for upsert.
    $data = '';
    $response_body = $this->response->getBody()->getContents();
    if (!empty($response_body)) {
      $this->response->getBody()->rewind();
      $data = $this->handleJsonResponse($this->response);
    }
    return $data;
  }

  /**
   * Private helper to issue an SF API request.
   *
   * @param string $path
   *   Path to resource.
   * @param array $params
   *   Parameters to provide.
   * @param string $method
   *   Method to initiate the call, such as GET or POST.  Defaults to GET.
   *
   * @return object
   *   The requested data.
   */
  protected function apiHttpRequest($path, array $params, $method) {
    if (!$this->getAccessToken()) {
      throw new Exception('Missing OAuth Token');
    }
    $url = $this->getApiEndPoint() . $path;

    $headers = [
      'Authorization' => 'OAuth ' . $this->getAccessToken(),
      'Content-type' => 'application/json',
    ];
    $data = NULL;
    if (!empty($params)) {
      // @TODO: convert this into Dependency Injection
      $data =  Json::encode($params);
    }
    return $this->httpRequest($url, $data, $headers, $method);
  }

  /**
   * Make the HTTP request. Wrapper around drupal_http_request().
   *
   * @param string $url
   *   Path to make request from.
   * @param array $data
   *   The request body.
   * @param array $headers
   *   Request headers to send as name => value.
   * @param string $method
   *   Method to initiate the call, such as GET or POST.  Defaults to GET.
   *
   * @throws RequestException
   *
   * @return object
   *   Salesforce response object.
   */
  protected function httpRequest($url, $data = NULL, array $headers = [], $method = 'GET') {
    // Build the request, including path and headers. Internal use.
    $request = $this->httpClient->$method($url, ['headers' => $headers, 'body' => $data]);
    return $request;
  }

  /**
   * Get the API end point for a given type of the API.
   *
   * @param string $api_type
   *   E.g., rest, partner, enterprise.
   *
   * @return string
   *   Complete URL endpoint for API access.
   */
  public function getApiEndPoint($api_type = 'rest') {
    $url = &drupal_static(__FUNCTION__ . $api_type);
    if (!isset($url)) {
      $identity = $this->getIdentity();
      if (is_string($identity)) {
        $url = $identity;
      }
      elseif (isset($identity['urls'][$api_type])) {
        $url = $identity['urls'][$api_type];
      }
      $url = str_replace('{version}', $this->config->get('rest_api_version.version'), $url);
    }
    return $url;
  }

  public function getConsumerKey() {
    return $this->state->get('salesforce.consumer_key');
  }

  public function setConsumerKey($value) {
    return $this->state->set('salesforce.consumer_key', $value);
  }

  public function getConsumerSecret() {
    return $this->state->get('salesforce.consumer_secret');
  }

  public function setConsumerSecret($value) {
    return $this->state->set('salesforce.consumer_secret', $value);
  }

  public function getLoginUrl() {
    $login_url = $this->state->get('salesforce.login_url');
    return empty($login_url) ? 'https://login.salesforce.com' : $login_url;
  }

  public function setLoginUrl($value) {
    return $this->state->set('salesforce.login_url', $value);
  }

  /**
   * Get the SF instance URL. Useful for linking to objects.
   */
  public function getInstanceUrl() {
    return $this->state->get('salesforce.instance_url');
  }

  /**
   * Set the SF instanc URL.
   *
   * @param string $url
   *   URL to set.
   */
  protected function setInstanceUrl($url) {
    $this->state->set('salesforce.instance_url', $url);
  }

  /**
   * Get the access token.
   */
  public function getAccessToken() {
    $access_token = $this->state->get('salesforce.access_token');
    return isset($access_token) && Unicode::strlen($access_token) !== 0 ? $access_token : FALSE;
  }

  /**
   * Set the access token.
   *
   * @param string $token
   *   Access token from Salesforce.
   */
  public function setAccessToken($token) {
    $this->state->set('salesforce.access_token', $token);
  }

  /**
   * Get refresh token.
   */
  protected function getRefreshToken() {
    return $this->state->get('salesforce.refresh_token');
  }

  /**
   * Set refresh token.
   *
   * @param string $token
   *   Refresh token from Salesforce.
   */
  protected function setRefreshToken($token) {
    $this->state->set('salesforce.refresh_token', $token);
  }

  /**
   * Refresh access token based on the refresh token.
   *
   * @throws Exception
   */
  protected function refreshToken() {
    $refresh_token = $this->getRefreshToken();
    if (empty($refresh_token)) {
      throw new Exception(t('There is no refresh token.'));
    }

    $data = UrlHelper::buildQuery([
      'grant_type' => 'refresh_token',
      'refresh_token' => urldecode($refresh_token),
      'client_id' => $this->getConsumerKey(),
      'client_secret' => $this->getConsumerSecret(),
    ]);

    $url = $this->getAuthTokenUrl();
    $headers = [
      // This is an undocumented requirement on Salesforce's end.
      'Content-Type' => 'application/x-www-form-urlencoded',
    ];
    $response = $this->httpRequest($url, $data, $headers, 'POST');

    $this->handleAuthResponse($response);
  }

  /**
   * Helper callback for OAuth handshake, and refreshToken()
   *
   * @param GuzzleHttp\Psr7\Response $response
   *   Response object from refreshToken or authToken endpoints
   *
   * @see SalesforceController::oauthCallback()
   * @see self::refreshToken()
   */
  public function handleAuthResponse(Response $response) {
    if ($response->getStatusCode() != 200) {
     throw new Exception($response->getReasonPhrase(), $response->getStatusCode());
    }

    $data = $this->handleJsonResponse($response);

    $this->setAccessToken($data['access_token']);
    $this->setRefreshToken($data['refresh_token']);
    $this->initializeIdentity($data['id']);
    $this->setInstanceUrl($data['instance_url']);
    // Do not overwrite an existing refresh token with an empty value.
    if (!empty($data['refresh_token'])) {
      $this->setRefreshToken($data['refresh_token']);
    }
  }

  /**
   * Helper function to eliminate repetitive json parsing.
   *
   * @param Response $response 
   * @return array
   * @throws Drupal\salesforce\Exception
   */
  private function handleJsonResponse($response) {
    // Allow any exceptions here to bubble up:
    $data = Json::decode($response->getBody()->getContents());
    if (empty($data)) {
      throw new Exception('Invalid response ' . print_r($response->getBody()->getContents(), 1));
    }

    if (isset($data['error'])) {
      throw new Exception($data['error_description'], $data['error']);
    }

    if (!empty($data[0]) && count($data) == 1) {
      $data = $data[0];
    }

    if (isset($data['error'])) {
      throw new Exception($data['error_description'], $data['error']);
    }

    if (!empty($data['errorCode'])) {
      throw new Exception($data['message'], $this->response->getStatusCode());
    }

    return $data;
  }

  /**
   * Retrieve and store the Salesforce identity given an ID url.
   *
   * @param string $id
   *   Identity URL.
   *
   * @throws Exception
   */
  public function initializeIdentity($id) {
    $headers = [
      'Authorization' => 'OAuth ' . $this->getAccessToken(),
      'Content-type' => 'application/json',
    ];
    $response = $this->httpRequest($id, NULL, $headers);

    if ($response->getStatusCode() != 200) {
      throw new Exception(t('Unable to access identity service.'), $response->getStatusCode());
    }

    $data = $this->handleJsonResponse($response);
    $this->setIdentity($data);
  }

  protected function setIdentity(array $data) {
    $this->state->set('salesforce.identity', $data);
  }

  /**
   * Return the Salesforce identity, which is stored in a variable.
   *
   * @return array
   *   Returns FALSE is no identity has been stored.
   */
  public function getIdentity() {
    return $this->state->get('salesforce.identity');
  }

  /**
   * Helper to build the redirect URL for OAUTH workflow.
   *
   * @return string
   *   Redirect URL.
   *
   * @see Drupal\salesforce\Controller\SalesforceController
   */
  public function getAuthCallbackUrl() {
    return \Drupal::url('salesforce.oauth_callback', [], [
      'absolute' => TRUE,
      'https' => TRUE,
    ]);
  }

  /**
   * Get Salesforce oauth login endpoint. (OAuth step 1)
   *
   * @return string
   *   REST OAuth Login URL.
   */
  public function getAuthEndpointUrl() {
    return $this->getLoginUrl() . '/services/oauth2/authorize';
  }

  /**
   * Get Salesforce oauth token endpoint. (OAuth step 2)
   *
   * @return string
   *   REST OAuth Token URL.
   */
  public function getAuthTokenUrl() {
    return $this->getLoginUrl() . '/services/oauth2/token';
  }

  /**
   * @defgroup salesforce_apicalls Wrapper calls around core apiCall()
   */

  /**
   * Available objects and their metadata for your organization's data.
   *
   * @param array $conditions
   *   Associative array of filters to apply to the returned objects. Filters
   *   are applied after the list is returned from Salesforce.
   * @param bool $reset
   *   Whether to reset the cache and retrieve a fresh version from Salesforce.
   *
   * @return array
   *   Available objects and metadata.
   *
   * @addtogroup salesforce_apicalls
   */
  public function objects(array $conditions = ['updateable' => TRUE], $reset = FALSE) {
    $cache = \Drupal::cache()->get('salesforce:objects');

    // Force the recreation of the cache when it is older than 5 minutes.
    if ($cache && REQUEST_TIME < ($cache->created + 300) && !$reset) {
      $result = $cache->data;
    }
    else {
      $result = $this->apiCall('sobjects');
      \Drupal::cache()->set('salesforce:objects', $result, 0, ['salesforce']);
    }

    if (!empty($conditions)) {
      foreach ($result['sobjects'] as $key => $object) {
        foreach ($conditions as $condition => $value) {
          if (!$object[$condition] == $value) {
            unset($result['sobjects'][$key]);
          }
        }
      }
    }

    return $result['sobjects'];
  }

  /**
   * Use SOQL to get objects based on query string.
   *
   * @param SalesforceSelectQuery $query
   *   The constructed SOQL query.
   *
   * @return array
   *   Array of Salesforce objects that match the query.
   *
   * @addtogroup salesforce_apicalls
   */
  public function query(SelectQuery $query) {
    //$this->moduleHander->alter('salesforce_query', $query);
    // Casting $query as a string calls SalesforceSelectQuery::__toString().

    $result = $this->apiCall('query?q=' . (string) $query);
    return $result;
  }

  /**
   * Retreieve all the metadata for an object.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account, etc.
   * @param bool $reset
   *   Whether to reset the cache and retrieve a fresh version from Salesforce.
   *
   * @return array
   *   All the metadata for an object, including information about each field,
   *   URLs, and child relationships.
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectDescribe($name, $reset = FALSE) {
    if (empty($name)) {
      return [];
    }
    $cache = \Drupal::cache()->get('salesforce:object:' . $name);
    // Force the recreation of the cache when it is older than 5 minutes.
    if ($cache && REQUEST_TIME < ($cache->created + 300) && !$reset) {
      return $cache->data;
    }
    else {
      $object = $this->apiCall("sobjects/{$name}/describe");
      // Index fields by machine name, so we don't have to search every time.
      $new_fields = [];
      foreach ($object['fields'] as $field) {
        $new_fields[$field['name']] = $field;
      }
      $object['fields'] = $new_fields;
      \Drupal::cache()->set('salesforce:object:' . $name, $object, 0, ['salesforce']);
      return $object;
    }
  }

  /**
   * Create a new object of the given type.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account, etc.
   * @param array $params
   *   Values of the fields to set for the object.
   *
   * @return array
   *   "id" : "001D000000IqhSLIAZ",
   *   "errors" : [ ],
   *   "success" : true
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectCreate($name, array $params) {
    return $this->apiCall("sobjects/{$name}", $params, 'POST');
  }

  /**
   * Create new records or update existing records.
   *
   * The new records or updated records are based on the value of the specified
   * field.  If the value is not unique, REST API returns a 300 response with
   * the list of matching records and throws an Exception.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   * @param string $key
   *   The field to check if this record should be created or updated.
   * @param string $value
   *   The value for this record of the field specified for $key.
   * @param array $params
   *   Values of the fields to set for the object.
   *
   * @return array
   *   1) successful create:
   *     "id" : "00190000001pPvHAAU",
   *     "errors" : [ ],
   *     "success" : true
   *
   *   2) unsuccessful upsert:
   *     "message" : "The requested resource does not exist"
   *     "errorCode" : "NOT_FOUND"
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectUpsert($name, $key, $value, array $params) {
    // If key is set, remove from $params to avoid UPSERT errors.
    if (isset($params[$key])) {
      unset($params[$key]);
    }

    $data = $this->apiCall("sobjects/{$name}/{$key}/{$value}", $params, 'PATCH');

    // On update, upsert method returns an empty body. Retreive object id, so that we can return a consistent response.
    if ($this->response->getStatusCode() == 204) {
      // We need a way to allow callers to distinguish updates and inserts. To
      // that end, cache the original response and reset it after fetching the
      // ID.
      $response = $this->response;
      $sf_object = $this->objectReadbyExternalId($name, $key, $value);
      $data['id'] = $sf_object['Id'];
      $data['success'] = TRUE;
      $data['errors'] = [];
      $this->response = $response;
    }

    return $data;
  }

  /**
   * Update an existing object.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   * @param string $id
   *   Salesforce id of the object.
   * @param array $params
   *   Values of the fields to set for the object.
   *
   * @return null
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectUpdate($name, $id, array $params) {
    $this->apiCall("sobjects/{$name}/{$id}", $params, 'PATCH');
  }

  /**
   * Return a full loaded Salesforce object.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   * @param string $id
   *   Salesforce id of the object.
   *
   * @return array
   *   Object of the requested Salesforce object.
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectRead($name, $id) {
    return $this->apiCall("sobjects/{$name}/{$id}", [], 'GET');
  }

  /**
   * Return a full loaded Salesforce object from External ID.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   * @param string $field
   *   Salesforce external id field name.
   * @param string $value
   *   Value of external id.
   *
   * @return object
   *   Object of the requested Salesforce object.
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectReadbyExternalId($name, $field, $value) {
    return $this->apiCall("sobjects/{$name}/{$field}/{$value}");
  }

  /**
   * Delete a Salesforce object.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   * @param string $id
   *   Salesforce id of the object.
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectDelete($name, $id) {
    $this->apiCall("sobjects/{$name}/{$id}", [], 'DELETE');
  }

  /**
   * Return a list of available resources for the configured API version.
   *
   * @return array
   *   Associative array keyed by name with a URI value.
   *
   * @addtogroup salesforce_apicalls
   */
  public function listResources() {
    $resources = $this->apiCall('');
    foreach ($resources as $key => $path) {
      $items[$key] = $path;
    }
    return $items;
  }
}
