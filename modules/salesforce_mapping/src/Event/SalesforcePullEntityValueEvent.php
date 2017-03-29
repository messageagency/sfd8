<?php

namespace Drupal\salesforce_mapping\Event;

use Drupal\salesforce\Event\SalesforceBaseEvent;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface;

/**
 *
 */
class SalesforcePullEntityValueEvent extends SalesforceBaseEvent {

  protected $entity_value;
  protected $field_plugin;
  protected $mapped_object;
  protected $mapping;
  protected $entity;

  /**
   * undocumented function
   *
   * @param mixed &$value
   * @param SalesforceMappingFieldPluginInterface $field_plugin
   * @param MappedObjectInterface $mapped_object
   */
  public function __construct(&$value, SalesforceMappingFieldPluginInterface $field_plugin, MappedObjectInterface $mapped_object) {
    $this->entity_value = $value;
    $this->field_plugin = $field_plugin;
    $this->mapped_object = $mapped_object;
    $this->entity = $mapped_object->getMappedEntity();
    $this->mapping = $mapped_object->salesforce_mapping->entity;
  }

  public function getEntityValue() {
    return $this->entity_value;
  }

  public function getFieldPlugin() {
    return $this->field_plugin;
  }

  /**
   * @return EntityInterface (from PushParams)
   */
  public function getEntity() {
    return $this->entity;
  }

  /**
   * @return SalesforceMappingInterface (from PushParams)
   */
  public function getMapping() {
    return $this->mapping;
  }

  /**
   * @return MappedObjectInterface
   */
  public function getMappedObject() {
    return $this->mapped_object;
  }

  public function getOp() {
    return $this->op;
  }

}
