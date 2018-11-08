<?php

namespace Drupal\salesforce\Rest;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\salesforce\Exception;
use Drupal\salesforce\SelectQueryInterface;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SObject;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce\SalesforceAuthProviderInterface;
use Drupal\salesforce\SalesforceAuthManager;
use Drupal\salesforce\SalesforceAuthProviderPluginManager;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Drupal\Component\Datetime\TimeInterface;
use OAuth\Common\Storage\Exception\TokenNotFoundException;
use Zend\Diactoros\Exception\DeprecatedMethodException;

/**
 * Objects, properties, and methods to communicate with the Salesforce REST API.
 */
class RestClient implements RestClientInterface {

  /**
   * Response object.
   *
   * @var \GuzzleHttp\Psr7\Response
   */
  public $response;

  /**
   * GuzzleHttp client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Salesforce API URL.
   *
   * @var \Drupal\Core\Url
   */
  protected $url;

  /**
   * Salesforce mutable config object.  Useful for sets.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $mutableConfig;

  /**
   * Salesforce immutable config object.  Useful for gets.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $immutableConfig;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The JSON serializer service.
   *
   * @var \Drupal\Component\Serialization\Json
   */
  protected $json;

  /**
   * Auth provider manager.
   *
   * @var \Drupal\salesforce\SalesforceAuthProviderPluginManager
   */
  protected $authManager;

  /**
   * Active auth provider.
   *
   * @var \Drupal\salesforce\SalesforceAuthProviderInterface
   */
  protected $authProvider;

  /**
   * Active auth provider config.
   *
   * @var \Drupal\salesforce\Entity\SalesforceAuthConfig
   */
  protected $authConfig;

  /**
   * Active auth token.
   *
   * @var \OAuth\OAuth2\Token\TokenInterface
   */
  protected $authToken;

  protected $httpClientOptions;

  const CACHE_LIFETIME = 300;
  const LONGTERM_CACHE_LIFETIME = 86400;

  /**
   * Constructor which initializes the consumer.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The GuzzleHttp Client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \Drupal\Component\Serialization\Json $json
   *   The JSON serializer service.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, StateInterface $state, CacheBackendInterface $cache, Json $json, TimeInterface $time, SalesforceAuthProviderPluginManager $authManager) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->mutableConfig = $this->configFactory->getEditable('salesforce.settings');
    $this->immutableConfig = $this->configFactory->get('salesforce.settings');
    $this->state = $state;
    $this->cache = $cache;
    $this->json = $json;
    $this->time = $time;
    $this->httpClientOptions = [];
    $this->authManager = $authManager;
    $this->authProvider = $authManager->getProvider();
    $this->authConfig = $authManager->getConfig();
    $this->authToken = $authManager->getToken();
    return $this;
  }

  /**
   * Determine if this SF instance is fully configured.
   */
  public function isAuthorized() {
    try {
      return !is_null($this->authToken) && !empty($this->authToken->getAccessToken());
    }
    catch (TokenNotFoundException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function apiCall($path, array $params = [], $method = 'GET', $returnObject = FALSE) {
    if (!$this->isAuthorized()) {
      $this->authManager->refreshToken();
    }

    if (strpos($path, '/') === 0) {
      $url = $this->authProvider->getInstanceUrl() . $path;
    }
    else {
      $url = $this->getApiEndPoint() . $path;
    }

    try {
      $this->response = new RestResponse($this->apiHttpRequest($url, $params, $method));
    }
    catch (RequestException $e) {
      // RequestException gets thrown for any response status but 2XX.
      $this->response = $e->getResponse();

      // Any exceptions besides 401 get bubbled up.
      if (!$this->response || $this->response->getStatusCode() != 401) {
        throw new RestException($this->response, $e->getMessage(), $e->getCode(), $e);
      }
    }

    if ($this->response->getStatusCode() == 401) {
      // The session ID or OAuth token used has expired or is invalid: refresh
      // token. If refresh_token() throws an exception, or if apiHttpRequest()
      // throws anything but a RequestException, let it bubble up.
      $this->authManager->refreshToken();
      try {
        $this->response = new RestResponse($this->apiHttpRequest($url, $params, $method));
      }
      catch (RequestException $e) {
        $this->response = $e->getResponse();
        throw new RestException($this->response, $e->getMessage(), $e->getCode(), $e);
      }
    }

    if (empty($this->response)
    || ((int) floor($this->response->getStatusCode() / 100)) != 2) {
      throw new RestException($this->response, 'Unknown error occurred during API call');
    }

    $this->updateApiUsage($this->response);

    if ($returnObject) {
      return $this->response;
    }
    else {
      return $this->response->data;
    }
  }

  /**
   * Private helper to issue an SF API request.
   *
   * @param string $url
   *   Fully-qualified URL to resource.
   *
   * @param array $params
   *   Parameters to provide.
   * @param string $method
   *   Method to initiate the call, such as GET or POST.  Defaults to GET.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   Response object.
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\RequestException
   */
  protected function apiHttpRequest($url, array $params, $method) {
    if (!$this->isAuthorized()) {
      throw new \Exception('Missing OAuth Token');
    }

    $headers = [
      'Authorization' => 'OAuth ' . $this->authToken->getAccessToken(),
      'Content-type' => 'application/json',
    ];
    $data = NULL;
    if (!empty($params)) {
      $data = $this->json->encode($params);
    }
    return $this->httpRequest($url, $data, $headers, $method);
  }

  /**
   * Return raw response content from given URL. Useful for fetching data from
   * binary fields like Attachments.
   *
   * @param string $url
   * @return mixed
   * @throws \Exception
   */
  public function httpRequestRaw($url) {
    if (!$this->isAuthorized()) {
      throw new \Exception('Missing OAuth Token');
    }
    $headers = [
      'Authorization' => 'OAuth ' . $this->authToken->getAccessToken(),
      'Content-type' => 'application/json',
    ];
    $response = $this->httpRequest($url, NULL, $headers);
    return $response->getBody()->getContents();
  }

  /**
   * Make the HTTP request. Wrapper around drupal_http_request().
   *
   * @param string $url
   *   Path to make request from.
   * @param string $data
   *   The request body.
   * @param array $headers
   *   Request headers to send as name => value.
   * @param string $method
   *   Method to initiate the call, such as GET or POST.  Defaults to GET.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   Response object.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   *   Request exception.
   */
  protected function httpRequest($url, $data = NULL, array $headers = [], $method = 'GET') {
    // Build the request, including path and headers. Internal use.
    $args = NestedArray::mergeDeep($this->httpClientOptions, ['headers' => $headers, 'body' => $data]);
    return $this->httpClient->$method($url, $args);
  }

  /**
   * {@inheritdoc}
   */
  public function setHttpClientOptions(array $options) {
    $this->httpClientOptions = NestedArray::mergeDeep($this->httpClientOptions, $options);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setHttpClientOption($option_name, $option_value) {
    $this->httpClientOptions[$option_name] = $option_value;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getHttpClientOptions() {
    return $this->httpClientOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function getHttpClientOption($option_name) {
    return $this->httpClientOptions[$option_name];
  }

  /**
   * Extract normalized error information from a RequestException.
   *
   * @param \GuzzleHttp\Exception\RequestException $e
   *   Exception object.
   *
   * @return array
   *   Error array with keys:
   *   * message
   *   * errorCode
   *   * fields
   */
  protected function getErrorData(RequestException $e) {
    $response = $e->getResponse();
    $response_body = $response->getBody()->getContents();
    $data = $this->json->decode($response_body);
    if (!empty($data[0])) {
      $data = $data[0];
    }
    return $data;
  }

  /**
   * Get the API end point for a given type of the API.
   *
   * @param string $api_type
   *   E.g., rest, partner, enterprise.
   *
   * @return string
   *   Complete URL endpoint for API access, or FALSE if no identity is set.
   */
  public function getApiEndPoint($api_type = 'rest') {
    $url = &drupal_static(__FUNCTION__ . $api_type);
    if (!isset($url)) {
      $identity = $this->authProvider->getIdentity();
      if (empty($identity)) {
        return FALSE;
      }
      if (is_string($identity)) {
        $url = $identity;
      }
      elseif (isset($identity['urls'][$api_type])) {
        $url = $identity['urls'][$api_type];
      }
      $url = str_replace('{version}', $this->getApiVersion(), $url);
    }
    return $url;
  }

  /**
   * Wrapper for config rest_api_version.version.
   */
  public function getApiVersion() {
    if ($this->immutableConfig->get('use_latest')) {
      $versions = $this->getVersions();
      $version = end($versions);
      return $version['version'];
    }
    return $this->immutableConfig->get('rest_api_version.version');
  }

  /**
   * Setter for config salesforce.settings rest_api_version and use_latest.
   *
   * @param bool $use_latest
   * @param int $version
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\RequestException
   *
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getConfig() to access the current active auth configuration.
   */
  public function setApiVersion($use_latest = TRUE, $version = NULL) {
    trigger_error(__CLASS__.'::'.__FUNCTION__ . ' is deprecated and will be removed before the next stable release of Salesforce module. Please update your callers.', E_DEPRECATED);
    if ($use_latest) {
      $this->mutableConfig->set('use_latest', $use_latest);
    }
    else {
      $versions = $this->getVersions();
      if (empty($versions[$version])) {
        throw new \Exception("Version $version is not available.");
      }
      $version = $versions[$version];
      $this->mutableConfig->set('rest_api_version', $version);
    }
    $this->mutableConfig->save();
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getConfig() to access the current active auth configuration.
   */
  public function getConsumerKey() {
    return $this->authProvider ? $this->authProvider->getConsumerKey() : NULL;
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getConfig() to access the current active auth configuration.
   */
  public function setConsumerKey($value) {
    throw new DeprecatedMethodException(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated. See release notes.');
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getConfig() to access the current active auth configuration.
   */
  public function getConsumerSecret() {
    return $this->authProvider ? $this->authProvider->getConsumerSecret() : NULL;
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getConfig() to access the current active auth configuration.
   */
  public function setConsumerSecret($value) {
    throw new DeprecatedMethodException(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated. See release notes.');
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getConfig() to access the current active auth configuration.
   */
  public function getLoginUrl() {
    return $this->authProvider ? $this->authProvider->getLoginUrl() : NULL;
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getConfig() to access the current active auth configuration.
   */
  public function setLoginUrl($value) {
    throw new DeprecatedMethodException(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated. See release notes.');
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getToken() to access the current active auth token.
   */
  public function getInstanceUrl() {
    return $this->authProvider ? $this->authProvider->getInstanceUrl() : NULL;
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getToken() to access the current active auth token.
   */
  public function setInstanceUrl($url) {
    throw new DeprecatedMethodException(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated. See release notes.');
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getToken() to access the current active auth token.
   */
  public function getAccessToken() {
    return $this->authToken ? $this->authToken->getAccessToken() : NULL;
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getToken() to access the current active auth token.
   */
  public function setAccessToken($token) {
    throw new DeprecatedMethodException(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated. See release notes.');
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getToken() to access the current active auth token.
   */
  protected function getRefreshToken() {
    return $this->authToken ? $this->authToken->getRefreshToken() : NULL;
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getToken() to access the current active auth token.
   */
  public function setRefreshToken($token) {
    throw new DeprecatedMethodException(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated. See release notes.');
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getProvider() to access the current active auth configuration.
   */
  public function refreshToken() {
    return $this->authManager->refreshToken();
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getConfig() to access the current active auth configuration.
   */
  public function setIdentity($data) {
    throw new DeprecatedMethodException(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated. See release notes.');
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthManager::getConfig() to access the current active auth configuration.
   */
  public function getIdentity() {
    return $this->authProvider ? $this->authProvider->getIdentity() : NULL;
  }

  /**
   * Wrapper for "Versions" resource to list information about API releases.
   *
   * @param bool $reset
   *   Whether to reset cache.
   *
   * @return array
   *   Array of all available Salesforce versions, or empty array if version
   *   info is not available.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   */
  public function getVersions($reset = FALSE) {
    if (!$reset && ($cache = $this->cache->get('salesforce:versions'))) {
      return $cache->data;
    }

    $versions = [];
    $id = $this->authProvider->getIdentity();
    if (!empty($id)) {
      $url = str_replace('v{version}/', '', $id['urls']['rest']);
      $response = new RestResponse($this->httpRequest($url));
      foreach ($response->data as $version) {
        $versions[$version['version']] = $version;
      }
      $this->cache->set('salesforce:versions', $versions, $this->getRequestTime() + self::LONGTERM_CACHE_LIFETIME, ['salesforce']);
      return $versions;
    }
    else {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getApiUsage() {
    return $this->state->get('salesforce.usage');
  }

  /**
   * Helper method to extract API Usage info from response header and write to
   * stateful variable.
   *
   * @param RestResponse $response
   */
  protected function updateApiUsage(RestResponse $response) {
    if ($limit_info = $response->getHeader('Sforce-Limit-Info')) {
      if (is_array($limit_info)) {
        $limit_info = reset($limit_info);
      }
      $this->state->set('salesforce.usage', $limit_info);
    }
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
   *   Available objects and metadata, indexed by object table name.
   *
   * @addtogroup salesforce_apicalls
   */
  public function objects(array $conditions = ['updateable' => TRUE], $reset = FALSE) {
    // Use the cached data if we have it.
    if (!$reset && ($cache = $this->cache->get('salesforce:objects'))) {
      $result = $cache->data;
    }
    else {
      $result = $this->apiCall('sobjects');
      $this->cache->set('salesforce:objects', $result, $this->getRequestTime() + self::CACHE_LIFETIME, ['salesforce']);
    }
//print_r($result);
    $sobjects = [];
    // Filter the list by conditions, and assign SF table names as array keys.
    foreach ($result['sobjects'] as $key => $object) {
      if (empty($object['name'])) {
        print_r($object);
      }
      if (!empty($conditions)) {
        foreach ($conditions as $condition => $value) {
          if ($object[$condition] == $value) {
            $sobjects[$object['name']] = $object;
          }
        }
      }
    }
    return $sobjects;
  }

  /**
   * {@inheritdoc}
   */
  public function query(SelectQueryInterface $query) {
    // $this->moduleHandler->alter('salesforce_query', $query);
    // Casting $query as a string calls SelectQuery::__toString().
    return new SelectQueryResult($this->apiCall('query?q=' . (string) $query));
  }

  /**
   * {@inheritdoc}
   */
  public function queryAll(SelectQueryInterface $query) {
    return new SelectQueryResult($this->apiCall('queryAll?q=' . (string) $query));
  }

  /**
   * {@inheritdoc}
   */
  public function queryMore(SelectQueryResult $results) {
    if ($results->done()) {
      return new SelectQueryResult([
        'totalSize' => $results->size(),
        'done' => TRUE,
        'records' => [],
      ]);
    }
    $version_path = parse_url($this->getApiEndPoint(), PHP_URL_PATH);
    $next_records_url = str_replace($version_path, '', $results->nextRecordsUrl());
    return new SelectQueryResult($this->apiCall($next_records_url));
  }

  /**
   * Retrieve all the metadata for an object.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account, etc.
   * @param bool $reset
   *   Whether to reset the cache and retrieve a fresh version from Salesforce.
   *
   * @return \Drupal\salesforce\Rest\RestResponse_Describe
   *   Salesforce object description object.
   *
   * @throws \Exception
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectDescribe($name, $reset = FALSE) {
    if (empty($name)) {
      throw new \Exception('No name provided to describe');
    }

    if (!$reset && ($cache = $this->cache->get('salesforce:object:' . $name))) {
      return $cache->data;
    }
    else {
      $response = new RestResponse_Describe($this->apiCall("sobjects/{$name}/describe", [], 'GET', TRUE));
      $this->cache->set('salesforce:object:' . $name, $response, $this->getRequestTime() + self::CACHE_LIFETIME, ['salesforce']);
      return $response;
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
   * @return \Drupal\salesforce\SFID
   *   Salesforce ID object.
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectCreate($name, array $params) {
    $response = $this->apiCall("sobjects/{$name}", $params, 'POST', TRUE);
    $data = $response->data;
    return new SFID($data['id']);
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
   * @return \Drupal\salesforce\SFID|NULL
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectUpsert($name, $key, $value, array $params) {
    // If key is set, remove from $params to avoid UPSERT errors.
    if (isset($params[$key])) {
      unset($params[$key]);
    }

    $response = $this->apiCall("sobjects/{$name}/{$key}/{$value}", $params, 'PATCH', TRUE);

    // On update, upsert method returns an empty body. Retreive object id, so that we can return a consistent response.
    if ($response->getStatusCode() == 204) {
      // We need a way to allow callers to distinguish updates and inserts. To
      // that end, cache the original response and reset it after fetching the
      // ID.
      $this->original_response = $response;
      $sf_object = $this->objectReadbyExternalId($name, $key, $value);
      return $sf_object->id();
    }
    $data = $response->data;
    return new SFID($data['id']);
  }

  /**
   * Update an existing object.
   *
   * Update() doesn't return any data. Examine HTTP response or Exception.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   * @param string $id
   *   Salesforce id of the object.
   * @param array $params
   *   Values of the fields to set for the object.
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
   * @return \Drupal\salesforce\SObject
   *   Object of the requested Salesforce object.
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectRead($name, $id) {
    return new SObject($this->apiCall("sobjects/{$name}/{$id}"));
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
   * @return \Drupal\salesforce\SObject
   *   Object of the requested Salesforce object.
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectReadbyExternalId($name, $field, $value) {
    return new SObject($this->apiCall("sobjects/{$name}/{$field}/{$value}"));
  }

  /**
   * Delete a Salesforce object.
   *
   * Note: if Object with given $id doesn't exist,
   * objectDelete() will assume success unless $throw_exception is given.
   * Delete() doesn't return any data. Examine HTTP response or Exception.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   * @param string $id
   *   Salesforce id of the object.
   * @param bool $throw_exception
   *   (optional) If TRUE, 404 response code will cause RequestException to be
   *   thrown. Otherwise, hide those errors. Default is FALSE.
   *
   * @throws \Exception
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectDelete($name, $id, $throw_exception = FALSE) {
    try {
      $this->apiCall("sobjects/{$name}/{$id}", [], 'DELETE');
    }
    catch (RequestException $e) {
      if ($throw_exception || $e->getResponse()->getStatusCode() != 404) {
        throw $e;
      }
    }
  }

  /**
   * Retrieves the list of individual objects that have been deleted within the
   * given timespan for a specified object type.
   *
   * @param string $type
   *   Object type name, E.g., Contact, Account.
   * @param string $startDate
   *   Start date to check for deleted objects (in ISO 8601 format).
   * @param string $endDate
   *   End date to check for deleted objects (in ISO 8601 format).
   *
   * @return GetDeletedResult
   */
  public function getDeleted($type, $startDate, $endDate) {
    return $this->apiCall("sobjects/{$type}/deleted/?start={$startDate}&end={$endDate}");
  }

  /**
   * Return a list of available resources for the configured API version.
   *
   * @return \Drupal\salesforce\Rest\RestResponse_Resources
   *
   * @addtogroup salesforce_apicalls
   */
  public function listResources() {
    return new RestResponse_Resources($this->apiCall('', [], 'GET', TRUE));
  }

  /**
   * Return a list of SFIDs for the given object, which have been created or
   * updated in the given timeframe.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   * @param int $start
   *   Unix timestamp for older timeframe for updates.
   *   Defaults to "-29 days" if empty.
   * @param int $end
   *   Unix timestamp for end of timeframe for updates.
   *   Defaults to now if empty.
   *
   * @return array
   *   return array has 2 indexes:
   *     "ids": a list of SFIDs of those records which have been created or
   *       updated in the given timeframe.
   *     "latestDateCovered": ISO 8601 format timestamp (UTC) of the last date
   *       covered in the request.
   *
   * @see https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/resources_getupdated.htm
   *
   * @addtogroup salesforce_apicalls
   */
  public function getUpdated($name, $start = NULL, $end = NULL) {
    if (empty($start)) {
      $start = strtotime('-29 days');
    }
    $start = urlencode(gmdate(DATE_ATOM, $start));

    if (empty($end)) {
      $end = time();
    }
    $end = urlencode(gmdate(DATE_ATOM, $end));

    return $this->apiCall("sobjects/{$name}/updated/?start=$start&end=$end");
  }

  /**
   * Retrieve all record types for this org. If $name is provided, retrieve
   * record types for the given object type only.
   *
   * @param string $name
   *   Object type name, e.g. Contact, Account, etc.
   *
   * @return array
   *   If $name is given, an array of record types indexed by developer name.
   *   Otherwise, an array of record type arrays, indexed by object type name.
   *
   * @throws \Exception
   */
  public function getRecordTypes($name = NULL, $reset = FALSE) {
    if (!$reset && ($cache = $this->cache->get('salesforce:record_types'))) {
      $record_types = $cache->data;
    }
    else {
      $query = new SelectQuery('RecordType');
      $query->fields = ['Id', 'Name', 'DeveloperName', 'SobjectType'];
      $result = $this->query($query);
      $record_types = [];
      foreach ($result->records() as $rt) {
        $record_types[$rt->field('SobjectType')][$rt->field('DeveloperName')] = $rt;
      }
      $this->cache->set('salesforce:record_types', $record_types, $this->getRequestTime() + self::CACHE_LIFETIME, ['salesforce']);
    }

    if ($name != NULL) {
      if (!isset($record_types[$name])) {
        throw new \Exception("No record types for $name");
      }
      return $record_types[$name];
    }
    return $record_types;
  }

  /**
   * Given a DeveloperName and SObject Name, return the SFID of the
   * corresponding RecordType. DeveloperName doesn't change between Salesforce
   * environments, so it's safer to rely on compared to SFID.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   * @param string $devname
   *   RecordType DeveloperName, e.g. Donation, Membership, etc.
   * @param bool $reset
   *
   * @return \Drupal\salesforce\SFID
   *   The Salesforce ID of the given Record Type, or NULL.
   *
   * @throws \Exception if record type not found
   */
  public function getRecordTypeIdByDeveloperName($name, $devname, $reset = FALSE) {
    $record_types = $this->getRecordTypes();
    if (empty($record_types[$name][$devname])) {
      throw new \Exception("No record type $devname for $name");
    }
    return $record_types[$name][$devname]->id();
  }

  /**
   * Utility function to determine object type for given SFID.
   *
   * @param \Drupal\salesforce\SFID $id
   *   Salesforce object ID.
   *
   * @return string
   *   Object type's name.
   *
   * @throws \Exception
   *   If SFID doesn't match any object type.
   */
  public function getObjectTypeName(SFID $id) {
    $prefix = substr((string) $id, 0, 3);
    $describe = $this->objects();
    foreach ($describe as $object) {
      if ($prefix == $object['keyPrefix']) {
        return $object['name'];
      }
    }
    throw new \Exception('No matching object type');
  }

  /**
   * Returns REQUEST_TIME.
   *
   * @return int
   *   The REQUEST_TIME server variable.
   */
  protected function getRequestTime() {
    return $this->time->getRequestTime();
  }

}
