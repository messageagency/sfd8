<?php

namespace Drupal\salesforce_mapping\Entity;

use Drupal\Core\Entity\EntityInterface;

/**
 *
 */
interface SalesforceMappingInterface {
  // Placeholder interface.
  // @TODO figure out what to abstract out of SalesforceMapping

  public function __construct(array $values = [], $entity_type);

  public function __get($key);

  public function getFieldMappings();

  public function getFieldMapping(array $field);

  public function getSalesforceObjectType();

  public function getDrupalEntityType();

  public function getDrupalBundle();

  public function getPullFields();

  public function getPullFieldsArray();

  public function getPullTriggerDate();

  //public function id();

  public function checkTriggers(array $triggers);

  /**
   * Whether or not this mapping has defined an upsert key.
   *
   * @return bool
   */
  public function hasKey();

  /**
   * Return name of the Salesforce field which is the upsert key.
   *
   * @return string
   */
  public function getKeyField();

  /**
   * Return value for the field upon which to be upserted.
   *
   * @return string
   */
  public function getKeyValue(EntityInterface $entity);

}
