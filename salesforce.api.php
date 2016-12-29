<?php

/**
 * @file
 * These are the hooks that are invoked by the Salesforce core.
 *
 * Core hooks are typically called in all modules at once using
 * module_invoke_all().
 */

/**
 * Alter parameters mapped to a Salesforce object before syncing to Salesforce.
 *
 * @param array $params
 *   Associative array of key value pairs.
 * @param object $mapping
 *   Salesforce mapping object.
 * @param object $entity_wrapper
 *   EntityMetadataWrapper of entity being mapped.
 */
function hook_salesforce_push_params_alter(&$params, $mapping, $entity_wrapper) {

}

/**
 * Prevent push to SF for an entity.
 *
 * @param EntityInterface $entity 
 *   The type of entity the push is for.
 * @param SalesforceMappingInterface $mapping 
 *   The mapping being used for this push.
 * @param string $operation
 *   Constant for the Drupal operation that triggered the sync. 
 *   One of:
 *     SALESFORCE_MAPPING_SYNC_DRUPAL_CREATE
 *     SALESFORCE_MAPPING_SYNC_DRUPAL_UPDATE
 *     SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE
 *
 * @return bool
 *   FALSE if the entity should not be synced to Salesforce for the
 *   $sf_sync_trigger operation.
 */
function hook_salesforce_push_entity_allowed(EntityInterface $entity, SalesforceMappingInterface $mapping, $operation) {

}

/**
 * Alter the value being mapped to an entity property from a Salesforce object.
 *
 * @param string $value
 *   Salesforce field value.
 * @param array $field_map
 *   Associative array containing the field mapping in the form
 *   <code>
 *   'fieldmap_name' => array(
 *      'drupal_field' => array(
 *        'fieldmap_type' => 'property',
 *        'fieldmap_value' => 'first_name'
 *      ),
 *      'salesforce_field' => array()
 *   )
 *   </code>.
 * @param object $sf_object
 *   Fully loaded Salesforce object.
 */
function hook_salesforce_pull_entity_value_alter(&$value, $field_map, $sf_object) {

}

/**
 * Alter a SOQL select query before it is executed.
 *
 * @param SalesforceSelectQuery $query
 *   The query object to alter.
 */
function hook_salesforce_query_alter(SalesforceSelectQuery &$query) {

}

/**
 * Alter pull data before creating or updating Entity and Mapped Object.
 * Implementations should throw an Exception to halt processing of this record.
 *
 * When a Mapped Object exists and a Drupal entity is being updated,
 * $sf_record[$mapping->pull_trigger_date] is compared to entity->updated date:
 * if the Drupal entity was updated more recently than the Salesforce record,
 * the pull is ignored. To force a pull in this case, set the value
 * $sf_record["__salesforce_force_pull"] to TRUE.
 *
 * When a Mapped Object does not exist, a Drupal entity will be created. To
 * match an existing entity instead, implement this hook and replace $entity,
 * and set mapped_object->entity_id accordingly.
 * 
 * @param SObject $sf_record
 *   The data from SF about to be mapped to an entity and saved.
 * @param MappedObjectInterface $mapped_object
 *   The mapped object being created or updated. @see ::isNew()
 * @param EntityInterface $entity
 *   The entity being created or updated.
 *
 */
function hook_salesforce_pull_pre_pull_alter(SObject $sf_record, MappedObjectInterface $mapped_object, EntityInterface $entity) {

}

/**
 * @} salesforce_hooks
 */
