<?php

namespace Drupal\salesforce\Rest;

use Drupal\salesforce\SelectQueryInterface;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SelectQueryResult;

/**
 * Objects, properties, and methods to communicate with the Salesforce REST API.
 */
interface RestClientInterface {

  /**
   * Check if the client is ready to perform API calls.
   *
   * TRUE indicates that the various dependencies are in place to initiate an
   * API call. It does NOT indicate that the API call will be successful, or
   * return a 200 status.
   *
   * @return bool
   *   FALSE if the client is not initialized. TRUE otherwise.
   */
  public function isInit();

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
   * Get the api usage, as returned in the most recent API request header.
   *
   * @return string|null
   *   Returns the complete Sforce-Limit-Info header from a recent API request.
   *   e.g. "api-usage=123/45678"
   */
  public function getApiUsage();

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
   * @return array|false
   *   If $name is given, a record type array indexed by developer name.
   *   Otherwise, an array of record type arrays, indexed by object type name.
   *   FALSE if no record types found.
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
   * @return \Drupal\salesforce\SFID|false
   *   The Salesforce ID of the given Record Type, or FALSE if not found.
   */
  public function getRecordTypeIdByDeveloperName($name, $devname, $reset = FALSE);

  /**
   * Utility function to determine object type for given SFID.
   *
   * @param \Drupal\salesforce\SFID $id
   *   The SFID.
   *
   * @return string|false
   *   The object type name, or FALSE if not found.
   */
  public function getObjectTypeName(SFID $id);

}
