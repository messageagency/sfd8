<?php

namespace Drupal\salesforce;

/**
 * Defines events for Salesforce.
 *
 * @see \Drupal\salesforce\SalesforceEvents
 */
final class SalesforceEvents {


  /**
   * Previously hook_salesforce_push_mapping_object_alter
   */
  const PUSH_MAPPING_OBJECT = 'salesforce.push_mapping_object';

  /**
   * Previously hook_salesforce_push_entity_allowed
   */
  const PUSH_ALLOWED = 'salesforce.push_allowed';

  /**
   * Name of the event fired when building params to push to Salesforce.
   *
   * This event allows modules to add, change, or remove params before they're
   * pushed to Salesforce. The event listener method receives a
   * \Drupal\salesforce\SalesforceEvent instance.
   * Previously hook_salesforce_push_params_alter()
   *
   * @Event
   *
   * @var string
   */
  const PUSH_PARAMS = 'salesforce.push_params';

  /**
    * Hook_salesforce_push_success.
    */
  const PUSH_SUCCESS = 'salesforce.push_success';

  /**
   * Hook_salesforce_push_fail.
   */
  const PUSH_FAIL = 'salesforce.push_fail';

  /**
   * Previously hook_salesforce_pull_select_query_alter
   */
  const PULL_QUERY = 'salesforce.pull_query';

  /**
   * Previously hook_salesforce_pull_entity_value_alter
   */
  const PULL_ENTITY_VALUE = 'salesforce.pull_entity_value';

  /**
   * Previously hook_salesforce_pull_mapping_object_alter.
   * Invoked prior to mapping entity fields for a pull. Can be used, for
   * example, to alter SF object retrieved from Salesforce or to assign a
   * different Drupal entity.
   */
  const PULL_PREPULL = 'salesforce.pull_prepull';

  /**
   * Previously hook_salesforce_pull_entity_presave.
   * Invoked immediately prior to saving the pulled Drupal entity, after all
   * fields have been mapped and values assigned. Can be used, for example, to 
   * override mapping fields or implement data transformations.
   */
  const PULL_PRESAVE = 'salesforce.pull_presave';

}
