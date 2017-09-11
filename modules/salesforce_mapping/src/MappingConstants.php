<?php

namespace Drupal\salesforce_mapping;

/**
 * Defines events for Salesforce.
 *
 * @see \Drupal\salesforce\Event\SalesforceEvent
 */
final class MappingConstants {
  /**
   * Define when a data sync should take place for a given mapping.
   */
  const SALESFORCE_MAPPING_SYNC_DRUPAL_CREATE = 'push_create';
  const SALESFORCE_MAPPING_SYNC_DRUPAL_UPDATE = 'push_update';
  const SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE = 'push_delete';
  const SALESFORCE_MAPPING_SYNC_SF_CREATE = 'pull_create';
  const SALESFORCE_MAPPING_SYNC_SF_UPDATE = 'pull_update';
  const SALESFORCE_MAPPING_SYNC_SF_DELETE = 'pull_delete';

  const SALESFORCE_MAPPING_TRIGGER_MAX_LENGTH = 16;

  /**
   * Field mapping direction constants.
   */
  const SALESFORCE_MAPPING_DIRECTION_DRUPAL_SF = 'drupal_sf';
  const SALESFORCE_MAPPING_DIRECTION_SF_DRUPAL = 'sf_drupal';
  const SALESFORCE_MAPPING_DIRECTION_SYNC = 'sync';

  /**
   * Delimiter used in Salesforce multipicklists.
   */
  const SALESFORCE_MAPPING_ARRAY_DELIMITER = ';';

  /**
   * Field mapping maximum name length.
   */
  const SALESFORCE_MAPPING_NAME_LENGTH = 128;


  const SALESFORCE_MAPPING_STATUS_SUCCESS = 1;
  const SALESFORCE_MAPPING_STATUS_ERROR = 0;

}
