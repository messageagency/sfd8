<?php

namespace Drupal\salesforce_mapping\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\salesforce\Exception;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\salesforce_mapping\MappingConstants;

/**
 * Defines a Salesforce Mapping configuration entity class.
 *
 * @ConfigEntityType(
 *   id = "salesforce_mapping",
 *   label = @Translation("Salesforce Mapping"),
 *   module = "salesforce_mapping",
 *   handlers = {
 *     "storage" = "Drupal\salesforce_mapping\SalesforceMappingStorage",
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
 *    "async",
 *    "pull_trigger_date",
 *    "sync_triggers",
 *    "salesforce_object_type",
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

  /**
   * Only one bundle type for now.
   */
  protected $type = 'salesforce_mapping';

  /**
   * ID (machine name) of the Mapping.
   *
   * @note numeric id was removed
   *
   * @var string
   */
  protected $id;

  /**
   * Label of the Mapping.
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
   * @var int
   */
  protected $weight = 0;

  /**
   * Status flag for the mapping.
   *
   * @var bool
   */
  protected $status = TRUE;

  /**
   * @TODO what does "locked" mean?
   *
   * @var bool
   */
  protected $locked = FALSE;

  /**
   * Whether to push asychronous only:
   *   * If true, disable real-time push.
   *   * If false (default), attempt real-time push and enqueue failures for
   *     async push.
   *
   * Note this is different behavior compared to D7.
   *
   * @var bool
   */
  protected $async = FALSE;

  /**
   * The Salesforce field to use for determining whether or not to pull.
   *
   * @var string
   */
  protected $pull_trigger_date = 'LastModifiedDate';

  /**
   * The drupal entity type to which this mapping points.
   *
   * @var string
   */
  protected $drupal_entity_type;

  /**
   * The drupal entity bundle to which this mapping points.
   *
   * @var string
   */
  protected $drupal_bundle;

  /**
   * The salesforce object type to which this mapping points.
   *
   * @var string
   */
  protected $salesforce_object_type;

  /**
   * Salesforce field name for upsert key, if set. Otherwise FALSE.
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
  public function __get($key) {
    return $this->$key;
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
   * Given a Salesforce object, return an array of Drupal entity key-value pairs.
   *
   * @return array
   *   Array of SalesforceMappingFieldPluginInterface objects
   *
   * @see salesforce_pull_map_field (from d7)
   */
  public function getPullFields() {
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
   * Build array of pulled fields for given mapping
   *
   * @return array
   *   Array of Salesforce field names for building SOQL query
   */
  public function getPullFieldsArray() {
    return array_column($this->field_mappings, 'salesforce_field', 'salesforce_field');
  }

  /**
   * Get the name of the salesforce key field, or NULL if no key is set.
   */
  public function getKeyField() {
    return $this->key ? $this->key : FALSE;
  }

  /**
   * @return bool
   */
  public function hasKey() {
    return $this->key ? TRUE : FALSE;
  }

  /**
   * @return mixed
   */
  public function getKeyValue(EntityInterface $entity) {
    if (!$this->hasKey()) {
      throw new \Exception('No key defined for this mapping.');
    }

    // @TODO #fieldMappingField
    foreach ($this->getFieldMappings() as $field_plugin) {
      if ($field_plugin->get('salesforce_field') == $this->getKeyField()) {
        return $field_plugin->value($entity, $this);
      }
    }
    throw new \Exception(t('Key %key not found for this mapping.', ['%key' => $this->getKeyField()]));
  }

  /**
   * @return string
   */
  public function getSalesforceObjectType() {
    return $this->salesforce_object_type;
  }

  /**
   * @return string
   */
  public function getDrupalEntityType() {
    return $this->drupal_entity_type;
  }

  /**
   * @return string
   */
  public function getDrupalBundle() {
    return $this->drupal_bundle;
  }

  /**
   * @return array
   */
  public function getFieldMappings() {
    // @TODO #fieldMappingField
    $fields = [];
    foreach ($this->field_mappings as $field) {
      $fields[] = $this->fieldManager()->createInstance(
         $field['drupal_field_type'],
         $field
       );
    }
    return $fields;
  }

  /**
   * @return SalesforceMappingFieldPluginInterface
   */
  public function getFieldMapping(array $field) {
    return $this->fieldManager()->createInstance(
      $field['drupal_field_type'],
      $field['config']
    );
  }

  public function doesPush() {
    return $this->checkTriggers([
      MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_CREATE,
      MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_UPDATE,
      MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE
    ]);
  }

  public function doesPull() {
    return $this->checkTriggers([
      MappingConstants::SALESFORCE_MAPPING_SYNC_SF_CREATE,
      MappingConstants::SALESFORCE_MAPPING_SYNC_SF_UPDATE,
      MappingConstants::SALESFORCE_MAPPING_SYNC_SF_DELETE
    ]);
  }

  /**
   * @return bool
   *   TRUE if this mapping uses any of the given $triggers, otherwise FALSE.
   */
  public function checkTriggers(array $triggers) {
    foreach ($triggers as $trigger) {
      if (!empty($this->sync_triggers[$trigger])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  public function fieldManager() {
    return \Drupal::service('plugin.manager.salesforce_mapping_field');
  }

}
