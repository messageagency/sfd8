<?php

namespace Drupal\salesforce\Rest;

use Drupal\salesforce\SelectQueryInterface;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SelectQueryResult;
use GuzzleHttp\Psr7\Response;

/**
 * Objects, properties, and methods to communicate with the Salesforce REST API.
 */
interface RestClientInterface {

  /**
   * Determine if this SF instance is fully configured.
   *
   * @deprecated in 8.x-4.0 and does not have an exact analog, refer to \Drupal\salesforce\Consumer\SalesforceCredentials::isValid instead.
   */
  public function isAuthorized();

  /**
   * Make a call to the Salesforce REST API.
   *
   * @param string $path
   *   Path to resource.
   *   If $path begins with a slash, the resource will be considered absolute,
   *   and only the instance URL will be pre-pended. This can be used, for
   *   example, to issue an API call to a custom Apex Rest endpoint.
   *   If $path does not begin with a slash, the resource will be considered
   *   relative and the Rest API Endpoint will be pre-pended.
   * @param array $params
   *   Parameters to provide.
   * @param string $method
   *   Method to initiate the call, such as GET or POST.  Defaults to GET.
   * @param bool $returnObject
   *   If true, return a Drupal\salesforce\Rest\RestResponse;
   *   Otherwise, return json-decoded response body only.
   *   Defaults to FALSE for backwards compatibility.
   *
   * @return mixed
   *   Response object or response data.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   */
  public function apiCall($path, array $params = [], $method = 'GET', $returnObject = FALSE);

  /**
   * Return raw response content from given URL.
   *
   * Useful for fetching data from binary fields like Attachments.
   *
   * @param string $url
   *   The url to request.
   *
   * @return mixed
   *   The raw http response body.
   *
   * @throws \Exception
   */
  public function httpRequestRaw($url);

  /**
   * Set options for Guzzle HTTP client.
   *
   * @param array $options
   *   The options to pass through to guzzle, as an associative array of option
   *   names and option values.
   *
   * @see http://docs.guzzlephp.org/en/latest/request-options.html
   *
   * @return $this
   */
  public function setHttpClientOptions(array $options);

  /**
   * Set a single Guzzle HTTP client option.
   *
   * @param string $option_name
   *   The option name to set.
   * @param mixed $option_value
   *   The option value to set.
   *
   * @see setHttpClientOptions
   *
   * @return $this
   */
  public function setHttpClientOption($option_name, $option_value);

  /**
   * Getter for HTTP client options.
   *
   * @return mixed
   *   The client options from guzzle.
   */
  public function getHttpClientOptions();

  /**
   * Getter for a single, named HTTP client option.
   *
   * @param string $option_name
   *   The option name to get.
   *
   * @return mixed
   *   The client option from guzzle.
   */
  public function getHttpClientOption($option_name);

  /**
   * Get the API end point for a given type of the API.
   *
   * @param string $api_type
   *   E.g., rest, partner, enterprise.
   *
   * @return string
   *   Complete URL endpoint for API access.
   *
   * @deprecated in 8.x-4.0, use \Drupal\salesforce\SalesforceAuthProviderInterface::getApiEndpoint instead.
   */
  public function getApiEndPoint($api_type = 'rest');

  /**
   * Wrapper for config rest_api_version.version.
   *
   * @return string
   *   The SF API version.
   *
   * @deprecated in 8.x-4.0, use \Drupal\salesforce\SalesforceAuthProviderInterface::getApiVersion instead.
   */
  public function getApiVersion();

  /**
   * Setter for config salesforce.settings rest_api_version and use_latest.
   *
   * @param bool $use_latest
   *   Use the latest version, instead of an explicit version number.
   * @param int $version
   *   The explicit version number. Mutually exclusive with $use_latest.
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\RequestException
   *
   * @deprecated in 8.x-4.0 and does not have an exact analog, refer to \Drupal::config('salesforce.settings')->set('rest_api_version.version') instead.
   */
  public function setApiVersion($use_latest = TRUE, $version = NULL);

  /**
   * Get the api usage, as returned in the most recent API request header.
   *
   * @return string|null
   *   Returns the complete Sforce-Limit-Info header from a recent API request.
   *   e.g. "api-usage=123/45678"
   */
  public function getApiUsage();

  /**
   * Consumer key getter.
   *
   * @return string|null
   *   Consumer key.
   *
   * @deprecated in 8.x-4.0, use \Drupal\salesforce\SalesforceAuthProviderInterface::getConsumerKey instead.
   */
  public function getConsumerKey();

  /**
   * Consumer key setter.
   *
   * @param string $value
   *   Consumer key value.
   *
   * @return $this
   *
   * @deprecated in 8.x-4.0 and does not have an exact analog, refer to \Drupal\salesforce\SalesforceAuthProviderInterface instead.
   */
  public function setConsumerKey($value);

  /**
   * Comsumer secret getter.
   *
   * @return string|null
   *   Consumer secret.
   *
   * @deprecated in 8.x-4.0, use \Drupal\salesforce\SalesforceAuthProviderInterface::getConsumerSecret instead.
   */
  public function getConsumerSecret();

  /**
   * Consumer key setter.
   *
   * @param string $value
   *   Consumer secret value.
   *
   * @return $this
   *
   * @deprecated in 8.x-4.0 and does not have an exact analog, refer to \Drupal\salesforce\SalesforceAuthProviderInterface instead.
   */
  public function setConsumerSecret($value);

  /**
   * Login url getter.
   *
   * @return string|null
   *   Login url.
   *
   * @deprecated in 8.x-4.0, use \Drupal\salesforce\SalesforceAuthProviderInterface::getLoginUrl instead.
   */
  public function getLoginUrl();

  /**
   * Login url setter.
   *
   * @param string $value
   *   The login url.
   *
   * @return $this
   *
   * @deprecated in 8.x-4.0 and does not have an exact analog, refer to \Drupal\salesforce\SalesforceAuthProviderInterface instead.
   */
  public function setLoginUrl($value);

  /**
   * Get the SF instance URL. Useful for linking to objects.
   *
   * @return string|null
   *   The instance url.
   *
   * @deprecated in 8.x-4.0 and does not have an exact analog, refer to \Drupal\salesforce\getInstanceUrl instead.
   */
  public function getInstanceUrl();

  /**
   * Set the SF instance URL.
   *
   * @param string $url
   *   The url.
   *
   * @return $this
   *
   * @deprecated in 8.x-4.0 and does not have an exact analog, refer to \Drupal\salesforce\SalesforceAuthProviderInterface instead.
   */
  public function setInstanceUrl($url);

  /**
   * Get the access token.
   *
   * @return string|null
   *   The access token.
   *
   * @deprecated in 8.x-4.0 and does not have an exact analog, refer to \Drupal\salesforce\SalesforceAuthProviderInterface::getAccessToken instead.
   */
  public function getAccessToken();

  /**
   * Set the access token.
   *
   * @param string $token
   *   Access token from Salesforce.
   *
   * @deprecated in 8.x-4.0 and does not have an exact analog, refer to \Drupal\salesforce\SalesforceAuthProviderInterface instead.
   */
  public function setAccessToken($token);

  /**
   * Set the refresh token.
   *
   * @param string $token
   *   Refresh token from Salesforce.
   *
   * @deprecated in 8.x-4.0 and does not have an exact analog, refer to \Drupal\salesforce\SalesforceAuthProviderInterface instead.
   */
  public function setRefreshToken($token);

  /**
   * Refresh access token based on the refresh token.
   *
   * @throws \Exception
   *
   * @deprecated in 8.x-4.0, use \Drupal\salesforce\SalesforceAuthProviderInterface::refreshAccessToken instead.
   */
  public function refreshToken();

  /**
   * Helper callback for OAuth handshake, and refreshToken()
   *
   * @param \GuzzleHttp\Psr7\Response $response
   *   Response object from refreshToken or authToken endpoints.
   *
   * @see SalesforceController::oauthCallback()
   * @see self::refreshToken()
   *
   * @deprecated in 8.x-4.0 and does not have an exact analog, refer to \OAuth\Common\Http\Client\StreamClient::retrieveResponse instead.
   */
  public function handleAuthResponse(Response $response);

  /**
   * Retrieve and store the Salesforce identity given an ID url.
   *
   * @param string $id
   *   Identity URL.
   *
   * @throws \Exception
   *
   * @deprecated in 8.x-4.0 and does not have an exact analog, refer to \Drupal\salesforce\SalesforceAuthProviderPluginBase::parseIdentityResponse instead.
   */
  public function initializeIdentity($id);

  /**
   * Return the Salesforce identity, which is stored in a variable.
   *
   * @return array|FALSE
   *   Returns FALSE is no identity has been stored.
   *
   * @deprecated in 8.x-4.0, use \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface::retrieveIdentity instead.
   */
  public function getIdentity();

  /**
   * Set the Salesforce identity, which is stored in a variable.
   *
   * @deprecated in 8.x-4.0, use \Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface::storeIdentity instead.
   */
  public function setIdentity($data);

  /**
   * Helper to build the redirect URL for OAUTH workflow.
   *
   * @return string
   *   Redirect URL.
   *
   * @see \Drupal\salesforce\Controller\SalesforceController
   *
   * @deprecated in 8.x-4.0, use \Drupal\salesforce\Consumer\SalesforceCredentials::getCallbackUrl instead.
   */
  public function getAuthCallbackUrl();

  /**
   * Get Salesforce oauth login endpoint. (OAuth step 1)
   *
   * @return string
   *   REST OAuth Login URL.
   *
   * @deprecated in 8.x-4.0, use \Drupal\salesforce\SalesforceAuthProviderInterface::getAuthorizationEndpoint instead.
   */
  public function getAuthEndpointUrl();

  /**
   * Get Salesforce oauth token endpoint. (OAuth step 2)
   *
   * @return string
   *   REST OAuth Token URL.
   *
   * @deprecated in 8.x-4.0, use \Drupal\salesforce\SalesforceAuthProviderInterface::getAccessTokenEndpoint instead.
   */
  public function getAuthTokenUrl();

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
  public function getVersions($reset = FALSE);

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
  public function objects(array $conditions = ['updateable' => TRUE], $reset = FALSE);

  /**
   * Use SOQL to get objects based on query string.
   *
   * @param \Drupal\salesforce\SelectQueryInterface $query
   *   The constructed SOQL query.
   *
   * @return \Drupal\salesforce\SelectQueryResult
   *   The query result.
   *
   * @addtogroup salesforce_apicalls
   */
  public function query(SelectQueryInterface $query);

  /**
   * Same as ::query(), but also returns deleted or archived records.
   *
   * @param \Drupal\salesforce\SelectQueryInterface $query
   *   The constructed SOQL query.
   *
   * @return \Drupal\salesforce\SelectQueryResult
   *   The query result.
   *
   * @addtogroup salesforce_apicalls
   */
  public function queryAll(SelectQueryInterface $query);

  /**
   * Given a select query result, fetch the next results set, if it exists.
   *
   * @param \Drupal\salesforce\SelectQueryResult $results
   *   The query result which potentially has more records.
   *
   * @return \Drupal\salesforce\SelectQueryResult
   *   If there are no more results, $results->records will be empty.
   */
  public function queryMore(SelectQueryResult $results);

  /**
   * Retrieve all the metadata for an object.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account, etc.
   * @param bool $reset
   *   Whether to reset the cache and retrieve a fresh version from Salesforce.
   *
   * @return \Drupal\salesforce\Rest\RestResponseDescribe
   *   The describe result.
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectDescribe($name, $reset = FALSE);

  /**
   * Create a new object of the given type.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account, etc.
   * @param array $params
   *   Values of the fields to set for the object.
   *
   * @return \Drupal\salesforce\SFID
   *   The new object's SFID.
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectCreate($name, array $params);

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
   * @return \Drupal\salesforce\SFID
   *   The new object's SFID, if created. NULL if updated. This is not ideal,
   *   but this is how Salesforce's API works. Go upvote this idea to fix it:
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectUpsert($name, $key, $value, array $params);

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
   *   Update() doesn't return any data. Examine HTTP response or Exception.
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectUpdate($name, $id, array $params);

  /**
   * Return a fullly loaded Salesforce object.
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
  public function objectRead($name, $id);

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
  public function objectReadbyExternalId($name, $field, $value);

  /**
   * Delete a Salesforce object.
   *
   * Note: if Object with given $id doesn't exist, objectDelete() will assume
   * success unless $throw_exception is given.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   * @param string $id
   *   Salesforce id of the object.
   * @param bool $throw_exception
   *   (optional) If TRUE, 404 response code will cause RequestException to be
   *   thrown. Otherwise, hide those errors. Default is FALSE.
   *
   * @addtogroup salesforce_apicalls
   *
   * @return null
   *   Delete() doesn't return any data. Examine HTTP response or Exception.
   */
  public function objectDelete($name, $id, $throw_exception = FALSE);

  /**
   * Retrieves objects deleted within the given timeframe.
   *
   * @param string $type
   *   Object type name, E.g., Contact, Account.
   * @param string $startDate
   *   Start date to check for deleted objects (in ISO 8601 format).
   * @param string $endDate
   *   End date to check for deleted objects (in ISO 8601 format).
   *
   * @return array
   *   Response data.
   */
  public function getDeleted($type, $startDate, $endDate);

  /**
   * Return a list of available resources for the configured API version.
   *
   * @return \Drupal\salesforce\Rest\RestResponseResources
   *   The response.
   *
   * @addtogroup salesforce_apicalls
   */
  public function listResources();

  /**
   * Return a list of SFIDs for the given object for the given timeframe.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   * @param int $start
   *   Unix timestamp for older timeframe for updates.
   *   Defaults to "-29 days" if empty.
   * @param int $end
   *   Unix timestamp for end of timeframe for updates. Defaults to now.
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
  public function getUpdated($name, $start = NULL, $end = NULL);

  /**
   * Retrieve all record types for this org.
   *
   * If $name is provided, retrieve record types for the given object type only.
   *
   * @param string $name
   *   Object type name, e.g. Contact, Account, etc.
   *
   * @return array
   *   If $name is given, a record type array indexed by developer name.
   *   Otherwise, an array of record type arrays, indexed by object type name.
   */
  public function getRecordTypes($name = NULL);

  /**
   * Given a DeveloperName and SObject Name, return SFID of the RecordType.
   *
   * DeveloperName doesn't change between Salesforce environments, so it's
   * safer to rely on compared to SFID.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   * @param string $devname
   *   RecordType DeveloperName, e.g. Donation, Membership, etc.
   * @param bool $reset
   *   If true, clear the local cache and fetch record types from API.
   *
   * @return \Drupal\salesforce\SFID
   *   The Salesforce ID of the given Record Type, or null.
   *
   * @throws \Exception
   *   If record type is not found.
   */
  public function getRecordTypeIdByDeveloperName($name, $devname, $reset = FALSE);

  /**
   * Utility function to determine object type for given SFID.
   *
   * @param \Drupal\salesforce\SFID $id
   *   The SFID.
   *
   * @return string
   *   The object type name.
   *
   * @throws \Exception
   *   If SFID doesn't match any object type.
   */
  public function getObjectTypeName(SFID $id);

}
