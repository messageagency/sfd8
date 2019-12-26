<?php

namespace Drupal\salesforce\Event;

/**
 * Defines events for Salesforce.
 *
 * @see \Drupal\salesforce\Event\SalesforceEvents
 */
final class SalesforceEvents {

  /**
   * Dispatched before enqueueing or triggering an entity delete.
   *
   * Event listeners should call $event->disallowDelete() to prevent delete.
   *
   * The event listener method receives a
   * \Drupal\salesforce_mapping\Event\SalesforceDeleteAllowedEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const DELETE_ALLOWED = 'salesforce.delete_allowed';

  /**
   * Dispatched before enqueueing or triggering a push event.
   *
   * Event listeners should call $event->disallowPush() to prevent push.
   *
   * Previously hook_salesforce_push_mapping_object_alter().
   *
   * The event listener method receives a
   * \Drupal\salesforce_mapping\Event\SalesforcePushAllowedEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_ALLOWED = 'salesforce.push_allowed';

  /**
   * Dispatched immediately before processing a push event.
   *
   * Useful for injecting business logic into a MappedObject record, e.g. to
   * change the SFID before pushing to Salesforce.
   *
   * Previously hook_salesforce_push_mapping_object_alter().
   *
   * The event listener method receives a
   * \Drupal\salesforce_mapping\Event\SalesforcePushOpEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_MAPPING_OBJECT = 'salesforce.push_mapping_object';

  /**
   * Dispatched after building params to push to Salesforce.
   *
   * Allow modifying params before they're pushed to Salesforce.
   * Previously hook_salesforce_push_params_alter().
   *
   * The event listener method receives a
   * \Drupal\salesforce_mapping\Event\SalesforcePushParamsEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_PARAMS = 'salesforce.push_params';

  /**
   * Dispatched after successful push to Salesforce.
   *
   * Previously Hook_salesforce_push_success().
   *
   * The event listener method receives a
   * \Drupal\salesforce_mapping\Event\SalesforcePushParamsEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_SUCCESS = 'salesforce.push_success';

  /**
   * Dispatched after failed push to Salesforce.
   *
   * Previously hook_salesforce_push_fail().
   *
   * The event listener method receives a
   * \Drupal\salesforce_mapping\Event\SalesforcePushOpEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const PUSH_FAIL = 'salesforce.push_fail';

  /**
   * Dispatched before querying Salesforce to pull records.
   *
   * Previously hook_salesforce_pull_select_query_alter().
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
   * Dispatched before mapping entity fields for a pull.
   *
   * Can be used, for example, to alter SF object retrieved from Salesforce or
   * to assign a different Drupal entity.
   *
   * Previously hook_salesforce_pull_mapping_object_alter().
   *
   * Subscribers receive a Drupal\salesforce_mapping\Event\SalesforcePullEvent
   * instance. Listeners should throw an exception to prevent an item from being
   * pulled, per Drupal\Core\Queue\QueueWorkerInterface.
   *
   * @see \Drupal\Core\Queue\QueueWorkerInterface
   *
   * @Event
   *
   * @var string
   */
  const PULL_PREPULL = 'salesforce.pull_prepull';

  /**
   * Dispatched before assigning Drupal entity values during pull.
   *
   * Pull analog to PUSH_PARAMS.
   *
   * Previously hook_salesforce_pull_entity_value_alter().
   *
   * Subscribers receive a Drupal\salesforce_mapping\Event\SalesforcePullEvent
   * instance in order to modify pull field values or entities.
   *
   * @Event
   *
   * @var string
   */
  const PULL_ENTITY_VALUE = 'salesforce.pull_entity_value';

  /**
   * Dispatched immediately prior to saving the pulled Drupal entity.
   *
   * After all fields have been mapped and values assigned, can be used, for
   * example, to override mapping fields or implement data transformations.
   * Final chance for subscribers to prevent creation or alter a Drupal entity
   * during pull. Post-save operations (insert/update) should rely on
   * hook_entity_update or hook_entity_insert().
   *
   * Previously hook_salesforce_pull_entity_presave().
   *
   * Subscribers receive a Drupal\salesforce_mapping\Event\SalesforcePullEvent
   * instance.
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
   *
   * @Event
   *
   * @var string
   */
  const ERROR = 'salesforce.error';

  /**
   * Dispatched when Salesforce encounters a concerning, but non-error event.
   *
   * Subscribers receive a Drupal\salesforce\SalesforceWarningEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const WARNING = 'salesforce.warning';

  /**
   * Dispatched when Salesforce encounters a basic loggable event.
   *
   * Subscribers receive a Drupal\salesforce\SalesforceNoticeEvent instance.
   *
   * @Event
   *
   * @var string
   */
  const NOTICE = 'salesforce.notice';

}
