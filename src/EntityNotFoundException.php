<?php

namespace Drupal\salesforce;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Render\FormattableMarkup;
use Throwable;

/**
 * EntityNotFoundException extends Drupal\salesforce\Exception
 * Thrown when a mapped entity cannot be loaded.
 */
class EntityNotFoundException extends \RuntimeException {

  use StringTranslationTrait;

  protected $entity_properties;

  protected $entity_type_id;

  /**
   * EntityNotFoundException constructor.
   *
   * @param $entity_properties
   * @param $entity_type_id
   * @param Throwable|NULL $previous
   */
  public function __construct($entity_properties, $entity_type_id, Throwable $previous = NULL) {
    parent::__construct($this->t('Entity not found. type: %type properties: %props', ['%type' => $entity_type_id, '%props' => var_export($entity_properties, TRUE)]), 0, $previous);
    $this->entity_properties = $entity_properties;
    $this->entity_type_id = $entity_type_id;
  }

  /**
   * @return mixed
   */
  public function getEntityProperties() {
    return $this->entity_properties;
  }

  /**
   * @return mixed
   */
  public function getEntityTypeId() {
    return $this->entity_type_id;
  }

  /**
   * @return \Drupal\Component\Render\FormattableMarkup
   */
  public function getFormattableMessage() {
    return new FormattableMarkup('Entity not found. type: %type properties: %props', ['%type' => $this->entity_type_id, '%props' => var_export($this->entity_properties, TRUE)]);
  }

}
