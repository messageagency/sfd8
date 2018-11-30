<?php

/**
 * @file
 * Legacy documentation, mapping of old hook system to new Events system.
 *
 * @see SalesforceEvents.php
 * @see salesforce_example.module
 */

/**
 * Hooks deprecated between 7.x and 8.x with pointers to new solutions.
 *
 * @defgroup salesforce_deprecated
 * See SalesforceEvents.php for events documentation.
 * See salesforce_example for example Event Subscriber implementation.
 *
 * @{
 */

/**
 * Use the SalesforceMappingField plugin system.
 *
 * @see Drupal\salesforce_mapping\Plugin\SalesforceMappingField\Properties
 */
function hook_salesforce_mapping_fieldmap_type() {
}

/**
 * Use a Plugin API alter.
 *
 * @see https://api.drupal.org/api/drupal/core%21core.api.php/group/plugin_api/8.2.x
 */
function hook_salesforce_mapping_fieldmap_type_alter() {
}

/**
 * Implement an EventSubscriber on SalesforceEvents::PULL_QUERY.
 */
function hook_salesforce_pull_select_query_alter() {
}

/**
 * Implement an EventSubscriber on SalesforceEvents::PULL_PREPULL.
 */
function hook_salesforce_pull_mapping_object_alter() {
}

/**
 * Implement an EventSubscriber on SalesforceEvents::PULL_ENTITY_VALUE.
 */
function hook_salesforce_pull_entity_value_alter() {
}

/**
 * Implement an EventSubscriber on SalesforceEvents::PULL_PRESAVE.
 */
function hook_salesforce_pull_entity_presave() {
}

/**
 * Use hook_entity_update or hook_mapped_object_insert.
 */
function hook_salesforce_pull_entity_insert() {
}

/**
 * Use hook_entity_update or hook_mapped_object_update.
 */
function hook_salesforce_pull_entity_update() {
}

/**
 * Implement an EventSubscriber on SalesforceEvents::PUSH_ALLOWED.
 *
 * Throw an exception to indicate that push is not allowed.
 */
function hook_salesforce_push_entity_allowed() {
}

/**
 * Implement an EventSubscriber on  SalesforceEvents::PUSH_MAPPING_OBJECT.
 */
function hook_salesforce_push_mapping_object_alter() {
}

/**
 * Implement an EventSubscriber on SalesforceEvents::PUSH_PARAMS.
 */
function hook_salesforce_push_params_alter() {
}

/**
 * Implement an EventSubscriber on SalesforceEvents::PUSH_SUCCESS.
 */
function hook_salesforce_push_success() {
}

/**
 * Implement an EventSubscriber on SalesforceEvents::PUSH_FAIL.
 */
function hook_salesforce_push_fail() {
}

/**
 * No replacement.
 *
 * Entities must implement proper URIs to take advantage of Salesforce mapping
 * dynamic routing.
 */
function hook_salesforce_mapping_entity_uris_alter() {
}

/**
 * @} salesforce_deprecated
 */
