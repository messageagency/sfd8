<?php

namespace Drupal\salesforce;

/**
 * EntityNotFoundException extends Drupal\salesforce\Exception
 * Thrown when a load operation returns no results.
 */
class EntityNotFoundException extends \RuntimeException {

  protected $entity_properties;

  protected $entity_type_id;

  public function __construct($entity_properties, $entity_type_id, Throwable $previous = NULL) {
    parent::__construct(t('Entity not found. type: %type properties: %props', ['%type' => $entity_type_id, '%props' => var_export($entity_properties, TRUE)]), 0, $previous);
    $this->entity_properties = $entity_properties;
    $this->entity_type_id = $entity_type_id;
  }

  public function getEntityProperties() {
    return $this->entity_properties;
  }

  public function getEntityTypeId() {
    return $this->entity_type_id;
  }
  
}
