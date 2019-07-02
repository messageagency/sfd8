<?php

namespace Drupal\salesforce_mapping\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;

/**
 * Mapping between Drupal and Salesforce records.
 */
interface SalesforceMappingInterface extends ConfigEntityInterface, EntityWithPluginCollectionInterface {

  /**
   * Magic getter method for mapping properties.
   *
   * @param string $key
   *   The property to get.
   *
   * @return mixed
   *   The value.
   */
  public function __get($key);

  /**
   * Get all the mapped field plugins for this mapping.
   *
   * @return \Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface[]
   *   The fields.
   */
  public function getFieldMappings();

  /**
   * Given a field config, create an instance of a field mapping.
   *
   * @param array $field
   *   Field plugin definition. Keys are "drupal_field_type" and "config".
   *
   * @return \Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface
   *   The field.
   */
  public function getFieldMapping(array $field);

  /**
   * Get the Salesforce Object type name for this mapping, e.g. "Contact".
   *
   * @return string
   *   The object name.
   */
  public function getSalesforceObjectType();

  /**
   * Get the Drupal entity type name for this mapping, e.g. "node".
   *
   * @return string
   *   The entity type id.
   */
  public function getDrupalEntityType();

  /**
   * Get the Drupal bundle name for this mapping, e.g. "article".
   *
   * @return string
   *   The bundle.
   */
  public function getDrupalBundle();

  /**
   * Get all the field plugins which are configured to pull from Salesforce.
   *
   * @return \Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface[]
   *   Array of objects.
   */
  public function getPullFields();

  /**
   * Get a flat array of the field plugins which are configured to pull.
   *
   * @return array
   *   Keys and values are Salesforce field names.
   */
  public function getPullFieldsArray();

  /**
   * The Salesforce date field which determines whether to pull.
   *
   * @return string
   *   SF field name.
   */
  public function getPullTriggerDate();

  /**
   * Getter for push_standalone property.
   *
   * @return bool
   *   TRUE if this mapping is set to process push queue via a standalone
   *   endpoint instead of during cron.
   */
  public function doesPushStandalone();

  /**
   * Getter for push_standalone property.
   *
   * @return bool
   *   TRUE if this mapping is set to process push queue via a standalone
   *   endpoint instead of during cron.
   */
  public function doesPullStandalone();

  /**
   * Checks mappings for any push operation.
   *
   * @return bool
   *   TRUE if this mapping is configured to push.
   */
  public function doesPush();

  /**
   * Checks mappings for any pull operation.
   *
   * @return bool
   *   TRUE if this mapping is configured to pull.
   */
  public function doesPull();

  /**
   * Checks if mapping has any of the given triggers.
   *
   * @param array $triggers
   *   Collection of SALESFORCE_MAPPING_SYNC_* constants from MappingConstants.
   *
   * @see \Drupal\salesforce_mapping\MappingConstants
   *
   * @return bool
   *   TRUE if any of the given $triggers are enabled for this mapping.
   */
  public function checkTriggers(array $triggers);

  /**
   * Return TRUE if an upsert key is set for this mapping.
   *
   * @return bool
   *   Return TRUE if an upsert key is set for this mapping.
   */
  public function hasKey();

  /**
   * Return name of the Salesforce field which is the upsert key.
   *
   * @return string
   *   The upsert key Salesforce field name.
   */
  public function getKeyField();

  /**
   * Given a Drupal entity, get the value to be upserted.
   *
   * @return mixed
   *   The upsert field value.
   */
  public function getKeyValue(EntityInterface $entity);

  /**
   * Return the timestamp for the date of most recent delete processing.
   *
   * @return int|null
   *   Integer timestamp of last delete, or NULL if delete has not been run.
   */
  public function getLastDeleteTime();

  /**
   * Set this mapping as having been last processed for deletes at $time.
   *
   * @param int $time
   *   The delete time to set.
   *
   * @return $this
   */
  public function setLastDeleteTime($time);

  /**
   * Return the timestamp for the date of most recent pull processing.
   *
   * @return mixed
   *   Integer timestamp of last pull, or NULL if pull has not been run.
   */
  public function getLastPullTime();

  /**
   * Set this mapping as having been last pulled at $time.
   *
   * @param int $time
   *   The pull time to set.
   *
   * @return $this
   */
  public function setLastPullTime($time);

  /**
   * Get the timestamp when the next pull should be processed for this mapping.
   *
   * @return int
   *   The next pull time.
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
   *   "now".
   *
   * @return \Drupal\salesforce\SelectQuery
   *   The pull query.
   */
  public function getPullQuery(array $mapped_fields = [], $start = 0, $stop = 0);

  /**
   * Returns a timstamp when the push queue was last processed for this mapping.
   *
   * @return int|null
   *   The last push time, or NULL.
   */
  public function getLastPushTime();

  /**
   * Set the timestamp when the push queue was last process for this mapping.
   *
   * @param string $time
   *   The push time to set.
   *
   * @return $this
   */
  public function setLastPushTime($time);

  /**
   * Get the timestamp when the next push should be processed for this mapping.
   *
   * @return int
   *   The next push time.
   */
  public function getNextPushTime();

  /**
   * Return TRUE if this mapping should always use upsert over create or update.
   *
   * @return bool
   *   Whether to upsert, ignoring any local Salesforce ID.
   */
  public function alwaysUpsert();

}
