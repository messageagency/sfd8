<?php

namespace Drupal\salesforce\Rest;

use Drupal\salesforce\SFID;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\SelectQueryResult;
use GuzzleHttp\Psr7\Response;

/**
 * Objects, properties, and methods to communicate with the Salesforce REST API.
 */
interface RestClientInterface {

  /**
   * Determine if this SF instance is fully configured.
   *
   * @TODO: Consider making a test API call.
   */
  public function isAuthorized();

  /**
   * Make a call to the Salesforce REST API.
   *
   * @param string $path
   *   Path to resource.
   *
   *   If $path begins with a slash, the resource will be considered absolute,
   *   and only the instance URL will be pre-pended. This can be used, for
   *   example, to issue an API call to a custom Apex Rest endpoint.
   *
   *   If $path does not begin with a slash, the resource will be considered
   *   relative and the Rest API Endpoint will be pre-pended.
   *
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
   *   Reponce object or response data.
   *
   * @throws GuzzleHttp\Exception\RequestException
   */
  public function apiCall($path, array $params = [], $method = 'GET', $returnObject = FALSE);


  /**
   * Set options for Guzzle HTTP client.
   * @see http://docs.guzzlephp.org/en/latest/request-options.html
   *
   * @param array $options
   * @return $this
   */
  public function setHttpClientOptions(array $options);

  /**
   * Set a single Guzzle HTTP client option.
   * @see setHttpClientOptions
   *
   * @param string $option_name 
   * @param mixed $option_value 
   * @return $this
   */
  public function setHttpClientOption($option_name, $option_value);

  /**
   * Getter for HTTP client options
   *
   * @return mixed
   */
  public function getHttpClientOptions();

  /**
   * Getter for a single, named HTTP client option
   *
   * @param string $option_name
   * @return mixed
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
   */
  public function getApiEndPoint($api_type = 'rest');

  /**
   * Get the api usage, as returned in the most recent API request header.
   *
   * @return string
   *   Returns the complete Sforce-Limit-Info header from a recent API request.
   *   e.g. "api-usage=123/45678"
   */
  public function getApiUsage();

  /**
   *
   */
  public function getConsumerKey();

  /**
   *
   */
  public function setConsumerKey($value);

  /**
   *
   */
  public function getConsumerSecret();

  /**
   *
   */
  public function setConsumerSecret($value);

  /**
   *
   */
  public function getLoginUrl();

  /**
   *
   */
  public function setLoginUrl($value);

  /**
   * Get the SF instance URL. Useful for linking to objects.
   */
  public function getInstanceUrl();

  /**
   * Get the access token.
   */
  public function getAccessToken();

  /**
   * Set the access token.
   *
   * @param string $token
   *   Access token from Salesforce.
   */
  public function setAccessToken($token);

  /**
   * Refresh access token based on the refresh token.
   *
   * @throws Exception
   */
  public function refreshToken();

  /**
   * Helper callback for OAuth handshake, and refreshToken()
   *
   * @param GuzzleHttp\Psr7\Response $response
   *   Response object from refreshToken or authToken endpoints.
   *
   * @see SalesforceController::oauthCallback()
   * @see self::refreshToken()
   */
  public function handleAuthResponse(Response $response);

  /**
   * Retrieve and store the Salesforce identity given an ID url.
   *
   * @param string $id
   *   Identity URL.
   *
   * @throws Exception
   */
  public function initializeIdentity($id);

  /**
   * Return the Salesforce identity, which is stored in a variable.
   *
   * @return array
   *   Returns FALSE is no identity has been stored.
   */
  public function getIdentity();

  /**
   * Helper to build the redirect URL for OAUTH workflow.
   *
   * @return string
   *   Redirect URL.
   *
   * @see Drupal\salesforce\Controller\SalesforceController
   */
  public function getAuthCallbackUrl();

  /**
   * Get Salesforce oauth login endpoint. (OAuth step 1)
   *
   * @return string
   *   REST OAuth Login URL.
   */
  public function getAuthEndpointUrl();

  /**
   * Get Salesforce oauth token endpoint. (OAuth step 2)
   *
   * @return string
   *   REST OAuth Token URL.
   */
  public function getAuthTokenUrl();

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
   * @param SelectQuery $query
   *   The constructed SOQL query.
   *
   * @return SelectQueryResult
   *
   * @addtogroup salesforce_apicalls
   */
  public function query(SelectQuery $query);

  /**
   * Given a select query result, fetch the next results set, if it exists.
   *
   * @param SelectQueryResult $results
   *   The query result which potentially has more records.
   *
   * @return SelectQueryResult
   *   If there are no more results, $results->records will be empty.
   */
  public function queryMore(SelectQueryResult $results);

  /**
   * Retreieve all the metadata for an object.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account, etc.
   * @param bool $reset
   *   Whether to reset the cache and retrieve a fresh version from Salesforce.
   *
   * @return RestResponse_Describe
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
   * @return Drupal\salesforce\SFID
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
   * @return Drupal\salesforce\SFID or NULL
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
   * Return a full loaded Salesforce object.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   * @param string $id
   *   Salesforce id of the object.
   *
   * @return SObject
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
   * @return SObject
   *   Object of the requested Salesforce object.
   *
   * @addtogroup salesforce_apicalls
   */
  public function objectReadbyExternalId($name, $field, $value);

  /**
   * Delete a Salesforce object. Note: if Object with given $id doesn't exist,
   * objectDelete() will assume success unless $throw_exception is given.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   * @param string $id
   *   Salesforce id of the object.
   * @pararm bool $throw_exception
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
   * Retrieves the list of individual objects that have been deleted within the
   * given timespan for a specified object type.
   *
   * @param string $type
   *   Object type name, E.g., Contact, Account.
   * @param string $startDate
   *   Start date to check for deleted objects (in ISO 8601 format).
   * @param string $endDate
   *   End date to check for deleted objects (in ISO 8601 format).
   * @return GetDeletedResult
   */
  public function getDeleted($type, $startDate, $endDate);

  /**
   * Return a list of available resources for the configured API version.
   *
   * @return Drupal\salesforce\Rest\RestResponse_Resources
   *
   * @addtogroup salesforce_apicalls
   */
  public function listResources();

  /**
   * Return a list of SFIDs for the given object, which have been created or
   * updated in the given timeframe.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   *
   * @param int $start
   *   Unix timestamp for older timeframe for updates.
   *   Defaults to "-29 days" if empty.
   *
   * @param int $end
   *   unix timestamp for end of timeframe for updates.
   *   Defaults to now if empty
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
  public function getUpdated($name, $start = null, $end = null);

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
   */
  public function getRecordTypes($name = NULL);

  /**
   * Given a DeveloperName and SObject Name, return the SFID of the
   * corresponding RecordType. DeveloperName doesn't change between Salesforce
   * environments, so it's safer to rely on compared to SFID.
   *
   * @param string $name
   *   Object type name, E.g., Contact, Account.
   *
   * @param string $devname
   *   RecordType DeveloperName, e.g. Donation, Membership, etc.
   *
   * @return SFID
   *   The Salesforce ID of the given Record Type, or null.
   *
   * @throws Exception if record type not found
   */
  public function getRecordTypeIdByDeveloperName($name, $devname, $reset = FALSE);

  /**
   * Utility function to determine object type for given SFID
   *
   * @param SFID $id
   * @return string
   * @throws Exception if SFID doesn't match any object type
   */
  public function getObjectTypeName(SFID $id);

}
