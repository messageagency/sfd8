<?php

namespace Drupal\salesforce\Rest;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\salesforce\Exception;
use Drupal\salesforce\SalesforceAuthProviderPluginManager;
use Drupal\salesforce\SelectQueryInterface;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SObject;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\SelectQueryResult;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Drupal\Component\Datetime\TimeInterface;

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

  protected $httpClientOptions;

  /**
   * Token storage.
   *
   * @var \Drupal\salesforce\Storage\SalesforceAuthTokenStorage
   *
   * @deprecated BC legacy auth scheme only, do not use, will be removed.
   */
  private $storage;

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
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The Time service.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, StateInterface $state, CacheBackendInterface $cache, Json $json, TimeInterface $time) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->mutableConfig = $this->configFactory->getEditable('salesforce.settings');
    $this->immutableConfig = $this->configFactory->get('salesforce.settings');
    $this->state = $state;
    $this->cache = $cache;
    $this->json = $json;
    $this->time = $time;
    $this->httpClientOptions = [];
    return $this;
  }

  /**
   * Storage helper.
   *
   * @return \Drupal\salesforce\Storage\SalesforceAuthTokenStorage
   *   The auth token storage service.
   *
   * @deprecated BC legacy auth scheme only, do not use, will be removed.
   */
  private function storage() {
    if (!$this->storage) {
      $this->storage = \Drupal::service('salesforce.auth_token_storage');
    }
    return $this->storage;
  }

  /**
   * {@inheritdoc}
   */
  public function isAuthorized() {
    return $this->getConsumerKey() && $this->getConsumerSecret() && $this->getRefreshToken();
  }

  /**
   * {@inheritdoc}
   */
  public function apiCall($path, array $params = [], $method = 'GET', $returnObject = FALSE) {
    if (!$this->getAccessToken()) {
      $this->refreshToken();
    }

    if (strpos($path, '/') === 0) {
      $url = $this->getInstanceUrl() . $path;
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
      // token. If refreshToken() throws an exception, or if apiHttpRequest()
      // throws anything but a RequestException, let it bubble up.
      $this->refreshToken();
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
    if (!$this->getAccessToken()) {
      throw new \Exception('Missing OAuth Token');
    }

    $headers = [
      'Authorization' => 'OAuth ' . $this->getAccessToken(),
      'Content-type' => 'application/json',
    ];
    $data = NULL;
    if (!empty($params)) {
      $data = $this->json->encode($params);
    }
    return $this->httpRequest($url, $data, $headers, $method);
  }

  /**
   * {@inheritdoc}
   */
  public function httpRequestRaw($url) {
    if (!$this->getAccessToken()) {
      throw new \Exception('Missing OAuth Token');
    }
    $headers = [
      'Authorization' => 'OAuth ' . $this->getAccessToken(),
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
   * {@inheritdoc}
   */
  public function getApiEndPoint($api_type = 'rest') {
    $url = &drupal_static(__FUNCTION__ . $api_type);
    if (!isset($url)) {
      $identity = $this->getIdentity();
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
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function setApiVersion($use_latest = TRUE, $version = NULL) {
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
   * {@inheritdoc}
   */
  public function getConsumerKey() {
    return $this->immutableConfig->get('consumer_key');
  }

  /**
   * {@inheritdoc}
   */
  public function setConsumerKey($value) {
    $this->mutableConfig->set('consumer_key', $value)->save();
    SalesforceAuthProviderPluginManager::updateAuthConfig();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getConsumerSecret() {
    return $this->immutableConfig->get('consumer_secret');
  }

  /**
   * {@inheritdoc}
   */
  public function setConsumerSecret($value) {
    $this->mutableConfig->set('consumer_secret', $value)->save();
    SalesforceAuthProviderPluginManager::updateAuthConfig();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLoginUrl() {
    $login_url = $this->immutableConfig->get('login_url');
    return empty($login_url) ? 'https://login.salesforce.com' : $login_url;
  }

  /**
   * {@inheritdoc}
   */
  public function setLoginUrl($value) {
    $this->mutableConfig->set('login_url', $value)->save();
    SalesforceAuthProviderPluginManager::updateAuthConfig();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getInstanceUrl() {
    return $this->state->get('salesforce.instance_url');
  }

  /**
   * {@inheritdoc}
   */
  public function setInstanceUrl($url) {
    $this->state->set('salesforce.instance_url', $url);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken() {
    $access_token = $this->state->get('salesforce.access_token');
    return isset($access_token) && mb_strlen($access_token) !== 0 ? $access_token : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setAccessToken($token) {
    $this->state->set('salesforce.access_token', $token);
    $this->storage()->updateToken();
    return $this;
  }

  /**
   * Get refresh token.
   */
  public function getRefreshToken() {
    return $this->state->get('salesforce.refresh_token');
  }

  /**
   * {@inheritdoc}
   */
  public function setRefreshToken($token) {
    $this->state->set('salesforce.refresh_token', $token);
    $this->storage()->updateToken();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function refreshToken() {
    $refresh_token = $this->getRefreshToken();
    if (empty($refresh_token)) {
      throw new \Exception(t('There is no refresh token.'));
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
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function handleAuthResponse(Response $response) {
    if ($response->getStatusCode() != 200) {
      throw new \Exception($response->getReasonPhrase(), $response->getStatusCode());
    }

    $data = (new RestResponse($response))->data;

    $this
      ->setAccessToken($data['access_token'])
      ->initializeIdentity($data['id'])
      ->setInstanceUrl($data['instance_url']);

    // Do not overwrite an existing refresh token with an empty value.
    if (!empty($data['refresh_token'])) {
      $this->setRefreshToken($data['refresh_token']);
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function initializeIdentity($id) {
    $headers = [
      'Authorization' => 'OAuth ' . $this->getAccessToken(),
      'Content-type' => 'application/json',
    ];
    $response = $this->httpRequest($id, NULL, $headers);

    if ($response->getStatusCode() != 200) {
      throw new \Exception(t('Unable to access identity service.'), $response->getStatusCode());
    }
    $data = (new RestResponse($response))->data;

    $this->setIdentity($data);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setIdentity($data) {
    $this->state->set('salesforce.identity', $data);
    $this->storage()->updateIdentity();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getIdentity() {
    $identity = $this->state->get('salesforce.identity');
    if (!$identity) {
      return FALSE;
    }
    $id_url_scheme = parse_url($identity['id']);
    $allowed_endpoint = $this->getLoginUrl();
    $allowed_endpoint_url_scheme = parse_url($allowed_endpoint);
    if ($this->immutableConfig->get('endpoint_verification') && $id_url_scheme['host'] != $allowed_endpoint_url_scheme['host']) {
      throw new Exception('Salesforce identity does not match salesforce endpoint: you need to re-authenticate.');
    }
    return $identity;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthCallbackUrl() {
    return Url::fromRoute('salesforce.oauth_callback', [], [
      'absolute' => TRUE,
      'https' => TRUE,
    ])->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthEndpointUrl() {
    return $this->getLoginUrl() . '/services/oauth2/authorize';
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthTokenUrl() {
    return $this->getLoginUrl() . '/services/oauth2/token';
  }

  /**
   * {@inheritdoc}
   */
  public function getVersions($reset = FALSE) {
    if (!$reset && ($cache = $this->cache->get('salesforce:versions'))) {
      return $cache->data;
    }

    $versions = [];
    $id = $this->getIdentity();
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
   * Helper to extract API Usage info from response header and write to state.
   *
   * @param \Drupal\salesforce\Rest\RestResponse $response
   *   A REST API response.
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
   * {@inheritdoc}
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
    // print_r($result);
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
      else {
        $sobjects[$object['name']] = $object;
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
   * {@inheritdoc}
   */
  public function objectDescribe($name, $reset = FALSE) {
    if (empty($name)) {
      throw new \Exception('No name provided to describe');
    }

    if (!$reset && ($cache = $this->cache->get('salesforce:object:' . $name))) {
      return $cache->data;
    }
    else {
      $response = new RestResponseDescribe($this->apiCall("sobjects/{$name}/describe", [], 'GET', TRUE));
      $this->cache->set('salesforce:object:' . $name, $response, $this->getRequestTime() + self::CACHE_LIFETIME, ['salesforce']);
      return $response;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function objectCreate($name, array $params) {
    $response = $this->apiCall("sobjects/{$name}", $params, 'POST', TRUE);
    $data = $response->data;
    return new SFID($data['id']);
  }

  /**
   * {@inheritdoc}
   */
  public function objectUpsert($name, $key, $value, array $params) {
    // If key is set, remove from $params to avoid UPSERT errors.
    if (isset($params[$key])) {
      unset($params[$key]);
    }

    $response = $this->apiCall("sobjects/{$name}/{$key}/{$value}", $params, 'PATCH', TRUE);

    // On update, upsert method returns an empty body. Retreive object id, so
    // that we can return a consistent response.
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
   * {@inheritdoc}
   */
  public function objectUpdate($name, $id, array $params) {
    $this->apiCall("sobjects/{$name}/{$id}", $params, 'PATCH');
  }

  /**
   * {@inheritdoc}
   */
  public function objectRead($name, $id) {
    return new SObject($this->apiCall("sobjects/{$name}/{$id}"));
  }

  /**
   * {@inheritdoc}
   */
  public function objectReadbyExternalId($name, $field, $value) {
    return new SObject($this->apiCall("sobjects/{$name}/{$field}/{$value}"));
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function getDeleted($type, $startDate, $endDate) {
    return $this->apiCall("sobjects/{$type}/deleted/?start={$startDate}&end={$endDate}");
  }

  /**
   * {@inheritdoc}
   */
  public function listResources() {
    return new RestResponseResources($this->apiCall('', [], 'GET', TRUE));
  }

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  public function getRecordTypeIdByDeveloperName($name, $devname, $reset = FALSE) {
    $record_types = $this->getRecordTypes($name, $reset);
    if (empty($record_types[$devname])) {
      throw new \Exception("No record type $devname for $name");
    }
    return $record_types[$devname]->id();
  }

  /**
   * {@inheritdoc}
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
