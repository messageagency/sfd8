<?php

namespace Drupal\salesforce_mapping\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\DefaultLazyPluginCollection;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\salesforce\Exception;
use Drupal\salesforce\SelectQuery;
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
 *   },
 *   admin_permission = "administer salesforce mapping",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "weight" = "weight",
 *   },
 *   config_export = {
 *    "id",
 *    "label",
 *    "weight",
 *    "type",
 *    "key",
 *    "async",
 *    "push_standalone",
 *    "pull_standalone",
 *    "pull_trigger_date",
 *    "pull_where_clause",
 *    "sync_triggers",
 *    "salesforce_object_type",
 *    "drupal_entity_type",
 *    "drupal_bundle",
 *    "field_mappings",
 *    "push_limit",
 *    "push_retries",
 *    "push_frequency",
 *    "pull_frequency",
 *    "always_upsert"
 *   },
 *   lookup_keys = {
 *     "drupal_entity_type",
 *     "drupal_bundle",
 *     "salesforce_object_type"
 *   }
 * )
 */
class SalesforceMapping extends ConfigEntityBase implements SalesforceMappingInterface {

  use StringTranslationTrait;

  /**
   * Only one bundle type for now.
   *
   * @var string
   */
  protected $type = 'salesforce_mapping';

  /**
   * ID (machine name) of the Mapping.
   *
   * @var string
   *
   * @note numeric id was removed
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
   * Whether to push asychronous.
   *
   *   - If true, disable real-time push.
   *   - If false (default), attempt real-time push and enqueue failures for
   *     async push.
   *
   * Note this is different behavior compared to D7.
   *
   * @var bool
   */
  protected $async = FALSE;

  /**
   * Whether a standalone push endpoint is enabled for this mapping.
   *
   * @var bool
   */
  protected $push_standalone = FALSE;

  /**
   * Whether a standalone push endpoint is enabled for this mapping.
   *
   * @var bool
   */
  protected $pull_standalone = FALSE;

  /**
   * The Salesforce field to use for determining whether or not to pull.
   *
   * @var string
   */
  protected $pull_trigger_date = 'LastModifiedDate';

  /**
   * Additional "where" logic to append to pull-polling query.
   *
   * @var string
   */
  protected $pull_where_clause = '';

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
   * If TRUE, always use "upsert" to push data to Salesforce.
   *
   * Otherwise use "upsert" only if upsert key is set and SFID is not available.
   *
   * @var bool
   */
  protected $always_upsert;

  /**
   * Mapped field plugins.
   *
   * @var \Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface[]
   */
  protected $field_mappings = [];

  /**
   * Active sync triggers.
   *
   * @var array
   */
  protected $sync_triggers = [];

  /**
   * Stateful push data for this mapping.
   *
   * @var array
   */
  protected $push_info;

  /**
   * Statefull pull data for this mapping.
   *
   * @var array
   */
  protected $pull_info;

  /**
   * How often (in seconds) to push with this mapping.
   *
   * @var int
   */
  protected $push_frequency = 0;

  /**
   * Maxmimum number of records to push during a batch.
   *
   * @var int
   */
  protected $push_limit = 0;

  /**
   * Maximum number of attempts to push a record before it's considered failed.
   *
   * @var string
   */
  protected $push_retries = 3;

  /**
   * How often (in seconds) to pull with this mapping.
   *
   * @var int
   */
  protected $pull_frequency = 0;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);
    $push_info = $this->state()->get('salesforce.mapping_push_info', []);
    if (empty($push_info[$this->id()])) {
      $push_info[$this->id()] = [
        'last_timestamp' => 0,
      ];
    }
    $this->push_info = $push_info[$this->id()];

    $pull_info = $this->state()->get('salesforce.mapping_pull_info', []);
    if (empty($pull_info[$this->id()])) {
      $pull_info[$this->id()] = [
        'last_pull_timestamp' => 0,
        'last_delete_timestamp' => 0,
      ];
    }
    $this->pull_info = $pull_info[$this->id()];
    foreach ($this->field_mappings as $i => &$field_mapping) {
      $field_mapping['id'] = $i;
      $field_mapping['mapping'] = $this;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __get($key) {
    return $this->$key;
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    if (empty($this->field_mappings)) {
      return [];
    }
    return [
      'field_mappings' => new DefaultLazyPluginCollection($this->fieldManager(), $this->field_mappings),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function toArray() {
    // Schema API complains during save() if field_mappings' mapping property
    // exists as a reference to the parent mapping. It's redundant anyway, so
    // we can delete it safely.
    // @TODO there's probably a way to do this with schema.yml, but I can't find it.
    $entity_array = parent::toArray();
    foreach ($entity_array['field_mappings'] as $i => &$value) {
      unset($value['mapping']);
    }
    return $entity_array;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    $this->updated = $this->getRequestTime();
    if (isset($this->is_new) && $this->is_new) {
      $this->created = $this->getRequestTime();
    }
    return parent::save();
  }

  /**
   * Testable func to return the request time server variable.
   *
   * @return int
   *   The request time.
   */
  protected function getRequestTime() {
    return \Drupal::time()->getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    // Update shared pull values across other mappings to same object type.
    $pull_mappings = $storage->loadByProperties([
      'salesforce_object_type' => $this->salesforce_object_type,
    ]);
    unset($pull_mappings[$this->id()]);
    foreach ($pull_mappings as $mapping) {
      if ($this->pull_frequency != $mapping->pull_frequency) {
        $mapping->pull_frequency = $this->pull_frequency;
        $mapping->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    // Include config dependencies on all mapped Drupal fields.
    $this->dependencies = array_intersect_key($this->dependencies, ['enforced' => '']);
    foreach ($this->getFieldMappings() as $field) {
      // Configuration entities need to depend on the providers of any plugins
      // that they store the configuration for. Default calculateDependencies()
      // method does not work, because our field_mapping plugins are anonymous,
      // indexed by numeric id only.
      $this->calculatePluginDependencies($field);
    }

    // Add a hard dependency on the mapping entity and bundle.
    if ($entity_type = $this->entityTypeManager()->getDefinition($this->getDrupalEntityType())) {
      $dependency = $entity_type->getBundleConfigDependency($this->getDrupalBundle());
      $this->addDependency($dependency['type'], $dependency['name']);
    }
    if ($this->doesPull()) {
      $this->addDependency('module', 'salesforce_pull');
    }
    if ($this->doesPush()) {
      $this->addDependency('module', 'salesforce_push');
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies) {
    parent::onDependencyRemoval($dependencies);

    // If the mapped entity type is being removed, we'll delete this mapping.
    $entity_type = $this->entityTypeManager()->getDefinition($this->getDrupalEntityType());
    $dependency = $entity_type->getBundleConfigDependency($this->getDrupalBundle());
    if (!empty($dependencies[$dependency['type']][$dependency['name']])) {
      return FALSE;
    }

    // Otherwise, ask each field mapping plugin if wants to remove itself.
    return $this->removePluginDependencies($dependencies);
  }

  /**
   * Delegate dependency removal events to field mappings plugins.
   *
   * @param array $dependencies
   *   Dependencies.
   */
  public function removePluginDependencies(array $dependencies) {
    $changed = FALSE;
    foreach ($this->getFieldMappings() as $i => $field) {
      if ($field->checkFieldMappingDependency($dependencies)) {
        $changed = TRUE;
        // If a plugin is dependent on the configuration being deleted, remove
        // the field mapping.
        unset($this->field_mappings[$i]);
      }
    }
    return $changed;
  }

  /**
   * {@inheritdoc}
   */
  public function getPullFields() {
    // @TODO This should probably be delegated to a field plugin bag?
    $fields = [];
    foreach ($this->getFieldMappings() as $i => $field_plugin) {
      // Skip fields that aren't being pulled from Salesforce.
      if (!$field_plugin->pull()) {
        continue;
      }
      $fields[$i] = $field_plugin;
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getPullFieldsArray() {
    return array_column($this->field_mappings, 'salesforce_field', 'salesforce_field');
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyField() {
    return $this->key ? $this->key : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function hasKey() {
    return $this->key ? TRUE : FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getKeyValue(EntityInterface $entity) {
    if (!$this->hasKey()) {
      throw new \Exception('No key defined for this mapping.');
    }

    // @TODO #fieldMappingField
    foreach ($this->getFieldMappings() as $i => $field_plugin) {
      if ($field_plugin->get('salesforce_field') == $this->getKeyField()) {
        return $field_plugin->value($entity, $this);
      }
    }
    throw new \Exception($this->t('Key %key not found for this mapping.', ['%key' => $this->getKeyField()]));
  }

  /**
   * {@inheritdoc}
   */
  public function getSalesforceObjectType() {
    return $this->salesforce_object_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalEntityType() {
    return $this->drupal_entity_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getDrupalBundle() {
    return $this->drupal_bundle;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMappings() {
    // @TODO #fieldMappingField
    $fields = [];
    foreach ($this->field_mappings as $i => $field) {
      $fields[$i] = $this->fieldManager()->createInstance(
         $field['drupal_field_type'],
         $field + ['mapping' => $this]
       );
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldMapping(array $field) {
    return $this->fieldManager()->createInstance(
      $field['drupal_field_type'],
      $field['config'] + ['mapping' => $this]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getPullTriggerDate() {
    return $this->pull_trigger_date;
  }

  /**
   * {@inheritdoc}
   */
  public function doesPushStandalone() {
    return $this->push_standalone;
  }

  /**
   * {@inheritdoc}
   */
  public function doesPullStandalone() {
    return $this->pull_standalone;
  }

  /**
   * {@inheritdoc}
   */
  public function doesPush() {
    return $this->checkTriggers([
      MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_CREATE,
      MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_UPDATE,
      MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function doesPull() {
    return $this->checkTriggers([
      MappingConstants::SALESFORCE_MAPPING_SYNC_SF_CREATE,
      MappingConstants::SALESFORCE_MAPPING_SYNC_SF_UPDATE,
      MappingConstants::SALESFORCE_MAPPING_SYNC_SF_DELETE,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function checkTriggers(array $triggers) {
    foreach ($triggers as $trigger) {
      if (!empty($this->sync_triggers[$trigger])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the name of this configuration object.
   *
   * @return string
   *   The name of the configuration object.
   */
  public function getName() {
    return $this->name;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastDeleteTime() {
    return $this->pull_info['last_delete_timestamp'] ? $this->pull_info['last_delete_timestamp'] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastDeleteTime($time) {
    return $this->setPullInfo('last_delete_timestamp', $time);
  }

  /**
   * {@inheritdoc}
   */
  public function getLastPullTime() {
    return $this->pull_info['last_pull_timestamp'] ? $this->pull_info['last_pull_timestamp'] : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setLastPullTime($time) {
    return $this->setPullInfo('last_pull_timestamp', $time);
  }

  /**
   * Setter for pull info.
   *
   * @param string $key
   *   The config id to set.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   */
  protected function setPullInfo($key, $value) {
    $this->pull_info[$key] = $value;
    $pull_info = $this->state()->get('salesforce.mapping_pull_info');
    $pull_info[$this->id()] = $this->pull_info;
    $this->state()->set('salesforce.mapping_pull_info', $pull_info);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNextPullTime() {
    return $this->pull_info['last_pull_timestamp'] + $this->pull_frequency;
  }

  /**
   * {@inheritdoc}
   */
  public function getLastPushTime() {
    return $this->push_info['last_timestamp'];
  }

  /**
   * {@inheritdoc}
   */
  public function setLastPushTime($time) {
    return $this->setPushInfo('last_timestamp', $time);
  }

  /**
   * Setter for pull info.
   *
   * @param string $key
   *   The config id to set.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   */
  protected function setPushInfo($key, $value) {
    $this->push_info[$key] = $value;
    $push_info = $this->state()->get('salesforce.mapping_push_info');
    $push_info[$this->id()] = $this->push_info;
    $this->state()->set('salesforce.mapping_push_info', $push_info);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getNextPushTime() {
    return $this->push_info['last_timestamp'] + $this->push_frequency;
  }

  /**
   * {@inheritdoc}
   */
  public function getPullQuery(array $mapped_fields = [], $start = 0, $stop = 0) {
    if (!$this->doesPull()) {
      throw new Exception('Mapping does not pull.');
    }
    $object_type = $this->getSalesforceObjectType();
    $soql = new SelectQuery($object_type);

    // Convert field mappings to SOQL.
    if (empty($mapped_fields)) {
      $mapped_fields = $this->getPullFieldsArray();
    }
    $soql->fields = $mapped_fields;
    $soql->fields[] = 'Id';
    $soql->fields[] = $this->getPullTriggerDate();

    $start = $start > 0 ? $start : $this->getLastPullTime();
    // If no lastupdate and no start window provided, get all records.
    if ($start) {
      $start = gmdate('Y-m-d\TH:i:s\Z', $start);
      $soql->addCondition($this->getPullTriggerDate(), $start, '>');
    }

    if ($stop) {
      $stop = gmdate('Y-m-d\TH:i:s\Z', $stop);
      $soql->addCondition($this->getPullTriggerDate(), $stop, '<');
    }

    if (!empty($this->pull_where_clause)) {
      $soql->conditions[] = [$this->pull_where_clause];
    }
    $soql->order[$this->getPullTriggerDate()] = 'ASC';
    return $soql;
  }

  /**
   * {@inheritdoc}
   */
  public function alwaysUpsert() {
    return $this->hasKey() && !empty($this->always_upsert);
  }

  /**
   * Salesforce Mapping Field Manager service.
   *
   * @return \Drupal\salesforce_mapping\SalesforceMappingFieldPluginManager
   *   The plugin.manager.salesforce_mapping_field service.
   */
  protected function fieldManager() {
    return \Drupal::service('plugin.manager.salesforce_mapping_field');
  }

  /**
   * Salesforce API client service.
   *
   * @return \Drupal\salesforce\Rest\RestClient
   *   The salesforce.client service.
   */
  protected function client() {
    return \Drupal::service('salesforce.client');
  }

  /**
   * State service.
   *
   * @return \Drupal\Core\State\StateInterface
   *   The state service.
   */
  protected function state() {
    return \Drupal::state();
  }

}
