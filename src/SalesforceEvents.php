<?php

namespace Drupal\salesforce;

/**
 * Defines events for Salesforce
 *
 * @see \Drupal\salesforce\SalesforceEvent
 */
final class SalesforceEvents {

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
<<<<<<< Updated upstream
=======
   * hook_salesforce_push_entity_allowed
   */
  const PUSH_CRUD_ALLOWED = 'salesforce.push_crud.allowed';

  /**
>>>>>>> Stashed changes
    * hook_salesforce_push_success
    */
  const PUSH_SUCCESS = 'salesforce.push_success';

  /**
   * hook_salesforce_push_fail
   */
  const PUSH_FAIL = 'salesforce.push_fail';  

  /**
   * hook_salesforce_pull_entity_presave
   */
  const PULL_PRESAVE = 'salesforce.pull_presave';

  /**
   * hook_salesforce_pull_entity_insert
   */
  const PULL_INSERT = 'salesforce.pull_insert';

  /**
   * hook_salesforce_pull_entity_update
   */
  const PULL_UPDATE = 'salesforce.pull_update';

}
