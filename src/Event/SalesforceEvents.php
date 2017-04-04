<?php

namespace Drupal\salesforce\Event;

/**
 * Defines events for Salesforce.
 *
 * @see \Drupal\salesforce\Event\SalesforceEvents
 */
class SalesforceEvents {

  /**
   * Previously hook_salesforce_push_mapping_object_alter
   * The event listener method receives a
   * \Drupal\salesforce_mapping\Event\SalesforcePushOpEvent instance.
   *
   * Event listeners should throw an exception to prevent push.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_ALLOWED = 'salesforce.push_allowed';

  /**
   * Previously hook_salesforce_push_mapping_object_alter
   * The event listener method receives a
   * \Drupal\salesforce_mapping\Event\SalesforcePushOpEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_MAPPING_OBJECT = 'salesforce.push_mapping_object';

  /**
   * Previously hook_salesforce_push_params_alter()
   * Event fired when building params to push to Salesforce. This event allows
   * modules to add, change, or remove params before they're pushed to
   * Salesforce. The event listener method receives a
   * \Drupal\salesforce_mapping\Event\SalesforcePushParamsEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_PARAMS = 'salesforce.push_params';

  /**
   * Hook_salesforce_push_success.
   * Event fired on successful push to Salesforce.
   * The event listener method receives a
   * \Drupal\salesforce_mapping\Event\SalesforcePushParamsEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_SUCCESS = 'salesforce.push_success';

  /**
   * Hook_salesforce_push_fail.
   * Event fired on failed push to Salesforce.
   * The event listener method receives a
   * \Drupal\salesforce_mapping\Event\SalesforcePushOpEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_FAIL = 'salesforce.push_fail';

  /**
   * Previously hook_salesforce_pull_select_query_alter
   *
   * Subscribers receive a Drupal\salesforce_mapping\Event\SalesforcePullEvent 
   * instance, via which Drupal\salesforce\SelectQuery may be altered before
   * building Salesforce Drupal\salesforce_pull\PullQueueItem items.
   *
   * @Event
   *
   * @var string
   */
  const PULL_QUERY = 'salesforce.pull_query';

  /**
   * Previously hook_salesforce_pull_mapping_object_alter.
   * Subscribers receive a Drupal\salesforce_mapping\Event\SalesforcePullEvent 
   * instance.
   *
   * Invoked prior to mapping entity fields for a pull. Can be used, for
   * example, to alter SF object retrieved from Salesforce or to assign a
   * different Drupal entity.
   *
   * Listeners should throw an exception to prevent an item from being pulled.
   *
   * @Event
   *
   * @var string
   */
  const PULL_PREPULL = 'salesforce.pull_prepull';

  /**
   * Previously hook_salesforce_pull_entity_value_alter
   * Subscribers receive a Drupal\salesforce_mapping\Event\SalesforcePullEvent 
   * instance in order to modify pull field values or entities.
   * Analogous to PUSH_PARAMS.
   *
   * @Event
   *
   * @var string
   */
  const PULL_ENTITY_VALUE = 'salesforce.pull_entity_value';

  /**
   * Previously hook_salesforce_pull_entity_presave.
   * Subscribers receive a Drupal\salesforce_mapping\Event\SalesforcePullEvent 
   * instance
   *
   * Invoked immediately prior to saving the pulled Drupal entity, after all
   * fields have been mapped and values assigned. Can be used, for example, to 
   * override mapping fields or implement data transformations. Final chance
   * for subscribers to prevent creation or alter a Drupal entity during pull.
   *
   * Post-save operations (insert/update) should rely on hook_entity_update or
   * hook_entity_insert
   *
   * @Event
   *
   * @var string
   */
  const PULL_PRESAVE = 'salesforce.pull_presave';

  /**
   * Dispatched when Salesforce encounters a loggable, non-fatal error.
   *
   * Subscribers receive a Drupal\salesforce\SalesforceErrorEvent instance.
   * @Event
   *
   * @var string
   */
  const ERROR = 'salesforce.error';

  /**
   * Dispatched when Salesforce encounters a concerning, but non-error event.
   *
   * Subscribers receive a Drupal\salesforce\SalesforceWarningEvent instance.
   * @Event
   *
   * @var string
   */
  const WARNING = 'salesforce.warning';

  /**
   * Dispatched when Salesforce encounters a basic loggable event.
   *
   * Subscribers receive a Drupal\salesforce\SalesforceNoticeEvent instance.
   * @Event
   *
   * @var string
   */
  const NOTICE = 'salesforce.error';

}
