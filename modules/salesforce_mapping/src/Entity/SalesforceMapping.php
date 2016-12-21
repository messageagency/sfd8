<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Entity\SalesforceMapping.
 */

namespace Drupal\salesforce_mapping\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce\Exception;

/**
 * Defines a Salesforce Mapping configuration entity class.
 *
 * @ConfigEntityType(
 *   id = "salesforce_mapping",
 *   label = @Translation("Salesforce Mapping"),
 *   module = "salesforce_mapping",
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "access" = "Drupal\salesforce_mapping\SalesforceMappingAccessController",
 *     "list_builder" = "Drupal\salesforce_mapping\SalesforceMappingList",
 *     "form" = {
 *       "add" = "Drupal\salesforce_mapping\Form\SalesforceMappingAddForm",
 *       "edit" = "Drupal\salesforce_mapping\Form\SalesforceMappingEditForm",
 *       "disable" = "Drupal\salesforce_mapping\Form\SalesforceMappingDisableForm",
 *       "delete" = "Drupal\salesforce_mapping\Form\SalesforceMappingDeleteForm",
 *       "enable" = "Drupal\salesforce_mapping\Form\SalesforceMappingEnableForm",
 *       "fields" = "Drupal\salesforce_mapping\Form\SalesforceMappingFieldsForm",
 *      }
 *   },
 *   admin_permission = "administer salesforce mapping",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "edit-form" = "/admin/structure/salesforce/mappings/manage/{salesforce_mapping}",
 *     "delete-form" = "/admin/structure/salesforce/mappings/manage/{salesforce_mapping}/delete"
 *   },
 *   config_export = {
 *    "id",
 *    "label",
 *    "weight",
 *    "locked",
 *    "status",
 *    "type",
 *    "key",
 *    "pull_trigger_date",
 *    "sync_triggers",
 *    "salesforce_object_type",
 *    "salesforce_record_type",
 *    "drupal_entity_type",
 *    "drupal_bundle",
 *    "field_mappings"
 *   },
 *   lookup_keys = {
 *     "drupal_entity_type",
 *     "drupal_bundle",
 *     "salesforce_object_type"
 *   }
 * )
 */
class SalesforceMapping extends ConfigEntityBase implements SalesforceMappingInterface {

  // Only one bundle type for now.
  protected $type = 'salesforce_mapping';

  /**
   * ID (machine name) of the Mapping
   * @note numeric id was removed
   *
   * @var string
   */
  protected $id;

  /**
   * Label of the Mapping
   *
   * @var string
   */
  protected $label;

  /**
   * The UUID for this entity.
   *
   * @var string
   */
  protected $uuid;

  /**
   * A default weight for the mapping.
   *
   * @var int (optional)
   */
  protected $weight = 0;

  /**
   * Status flag for the mapping.
   *
   * @var boolean
   */
  protected $status = TRUE;


  /**
   * The drupal entity type to which this mapping points
   *
   * @var string
   */
  protected $drupal_entity_type;

  /**
   * The drupal entity bundle to which this mapping points
   *
   * @var string
   */
  protected $drupal_bundle;

  /**
   * The salesforce object type to which this mapping points
   *
   * @var string
   */
  protected $salesforce_object_type;

  /**
   * The salesforce record type to which this mapping points, if applicable
   *
   * @var string (optional)
   */
  protected $salesforce_record_type = '';

  /**
   * Salesforce field name for upsert key, if set. Otherwise FALSE
   *
   * @var string
   */
  protected $key;

  /**
   * @TODO documentation
   */
  protected $field_mappings = [];
  protected $sync_triggers = [];

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values = [], $entity_type) {
    parent::__construct($values, $entity_type);
    // entities don't support Dependency Injection, so we have to build a hard
    // dependency on the container here.
    // @TODO figure out a better way to do this.
    $this->fieldManager = \Drupal::service('plugin.manager.salesforce_mapping_field');
  }

  /**
   * Save the entity.
   *
   * @return object
   *   The newly saved version of the entity.
   */
  public function save() {
    $this->updated = REQUEST_TIME;
    if (isset($this->is_new) && $this->is_new) {
      $this->created = REQUEST_TIME;
    }
    return parent::save();
  }

  /**
   * Given a Drupal entity, return an array of Salesforce key-value pairs
   *
   * @param object $entity
   *   Entity wrapper object.
   *
   * @return array
   *   Associative array of key value pairs.
   * @see salesforce_push_map_params (from d7)
   */
  public function getPushParams(EntityInterface $entity) {
    // @TODO This should probably be delegated to a field plugin bag?
    foreach ($this->getFieldMappings() as $field_plugin) {
      // Skip fields that aren't being pushed to Salesforce.
      if (!$field_plugin->push()) {
        continue;
      }
      $params[$field_plugin->config('salesforce_field')] = $field_plugin->value($entity);
    }
    // @TODO make this an event
    // drupal_alter('salesforce_push_params', $params, $mapping, $entity_wrapper);
    return $params;
  }

  /**
   * Given a Salesforce object, return an array of Drupal entity key-value pairs
   *
   * @param object $entity
   *   Entity wrapper object.
   *
   * @return array
   *   Associative array of key value pairs.
   * @see salesforce_pull_map_field (from d7)
   */
  public function getPullFields(EntityInterface $entity) {
    // @TODO This should probably be delegated to a field plugin bag?
    $fields = [];
    foreach ($this->getFieldMappings() as $field_plugin) {
      // Skip fields that aren't being pulled from Salesforce.
      if (!$field_plugin->pull()) {
        continue;
      }
      $fields[] = $field_plugin;
    }
    return $fields;
  }

  /**
   * Get the name of the salesforce key field, or NULL if no key is set.
   */
  public function getKeyField() {
    return $this->key ? $this->key : FALSE;
  }

  public function hasKey() {
    return $this->key ? TRUE : FALSE;
  }

  public function getKeyValue(EntityInterface $entity) {
    if (!$this->hasKey()) {
      throw new Exception('No key defined for this mapping.');
    }

    // @TODO #fieldMappingField
    foreach ($this->getFieldMappings() as $field_plugin) {
      if ($field_plugin->get('salesforce_field') == $this->getKeyField()) {
        return $field_plugin->value($entity);
      }
    }
    throw new Exception(t('Key %key not found for this mapping.', ['%key' => $this->getKeyField()]));
  }

  public function getSalesforceObjectType() {
    return $this->salesforce_object_type;
  }

  public function getFieldMappings() {
    // @TODO #fieldMappingField
    $mappings = [];
    foreach ($this->field_mappings as $field) {
       $mappings[] = $this->fieldManager->createInstance(
         $field['drupal_field_type'],
         $field
       );
    }
    return $mappings;
  }

  public function getFieldMapping(array $field) {
    return $this->fieldManager->createInstance(
      $field['drupal_field_type'],
      $field['config']
    );
  }

  public function pull() {

  }

  public function checkTriggers(array $triggers) {
    foreach ($triggers as $trigger) {
      if ($this->sync_triggers[$trigger] == 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
