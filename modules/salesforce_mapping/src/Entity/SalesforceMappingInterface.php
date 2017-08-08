<?php

namespace Drupal\salesforce_mapping\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 *
 */
interface SalesforceMappingInterface extends ConfigEntityInterface {
  // Placeholder interface.
  // @TODO figure out what to abstract out of SalesforceMapping

  /**
   * @param array $values
   * @param string $entity_type
   */
  public function __construct(array $values = [], $entity_type);

  /**
   * @param  string $key
   * @return mixed
   */
  public function __get($key);

  /**
   * @param  string $property_name
   * @return mixed
   */
  public function get($property_name);

  /**
   * @return array fieldmappings
   */
  public function getFieldMappings();

  /**
   * @param  array  $field
   * @return SalesforceMappingFieldPluginInterface
   */
  public function getFieldMapping(array $field);

  /**
   * @return string
   */
  public function getSalesforceObjectType();

  /**
   * @return string
   */
  public function getDrupalEntityType();

  /**
   * @return string
   */
  public function getDrupalBundle();

  /**
  * Given a Salesforce object, return an array of Drupal entity key-value pairs.
  *
  * @return array
  *   Array of SalesforceMappingFieldPluginInterface objects
  *
  * @see salesforce_pull_map_field (from d7)
  */
  public function getPullFields();

  /**
   * @return array
   */
  public function getPullFieldsArray();

  /**
   * @return string
   */
  public function getPullTriggerDate();

  /**
   * Return TRUE if this mapping is set to process push queue via a standalone
   * endpoint instead of during cron.
   */
  public function doesPushStandalone();

  /**
   * Checks mappings for any push operation positive
   *
   * @return boolean
   */
  public function doesPush();

  /**
   * Checks mappings for any pull operation positive
   *
   * @return boolean
   */
  public function doesPull();

  /**
   * Checks if mapping has any of the given triggers
   *
   * @param array $triggers
   * @return boolean
   *   TRUE if this mapping uses any of the given $triggers, otherwise FALSE.
   */
  public function checkTriggers(array $triggers);

  /**
   * Whether or not this mapping has defined an upsert key.
   *
   * @return bool
   */
  public function hasKey();

  /**
   * Return name of the Salesforce field which is the upsert key.
   *
   * @return string
   */
  public function getKeyField();

  /**
   * Return value for the field upon which to be upserted.
   *
   * @return mixed
   */
  public function getKeyValue(EntityInterface $entity);

  /**
   * Return the timestamp for the date of most recent delete processing for
   * this mapping, or NULL if it has never been processed.
   *
   * @return mixed
   *   integer timestamp of last delete, or NULL.
   */
  public function getLastDeleteTime();

  /**
   * Set this mapping as having been last processed for deletes at $time
   *
   * @param int $time
   * @return $this
   */
  public function setLastDeleteTime($time);

  /**
   * We keep track of when this mapping was last pulled with a state value.
   * Fetch the value.
   *
   * @return mixed
   *   integer timestamp of last pull, or NULL.
   */
  public function getLastPullTime();

  /**
   * Set this mapping as having been last pulled at $time.
   *
   * @param int $time 
   * @return $this
   */
  public function setLastPullTime($time);

  /**
   * Get the timestamp when the next pull should be processed for this mapping.
   *
   * @return int
   */
  public function getNextPullTime();

  /**
   * Generate a select query to pull records from Salesforce for this mapping.
   *
   * @param array $mapped_fields
   *   Fetch only these fields, if given, otherwise fetch all mapped fields.
   * @param int $start
   *   Timestamp of starting window from which to pull records. If omitted, use
   *   ::getLastPullTime()
   * @param int $stop
   *   Timestamp of ending window from which to pull records. If omitted, use 
   *   "now"
   *
   * @return \Drupal\salesforce\SelectQuery
   */
  public function getPullQuery(array $mapped_fields = [], $start = 0, $stop = 0);
  
  /**
   * Returns a timstamp when the push queue was last processed for this mapping.
   *
   * @return int
   */
  public function getLastPushTime();

  /**
   * Set the timestamp when the push queue was last process for this mapping.
   *
   * @param string $time 
   * @return $this
   */
  public function setLastPushTime($time);

  /**
   * Get the timestamp when the next push should be processed for this mapping.
   *
   * @return int
   */
  public function getNextPushTime();

}
