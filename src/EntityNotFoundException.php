<?php

namespace Drupal\salesforce;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Component\Render\FormattableMarkup;
use Throwable;

/**
 * EntityNotFoundException extends Drupal\salesforce\Exception.
 *
 * Thrown when a mapped entity cannot be loaded.
 */
class EntityNotFoundException extends \RuntimeException {

  use StringTranslationTrait;

  /**
   * A list of entity properties, for logging.
   *
   * @var mixed
   */
  protected $entityProperties;

  /**
   * Entity type id, for logging.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * EntityNotFoundException constructor.
   *
   * @param mixed $entityProperties
   *   Entity properties.
   * @param string $entityTypeId
   *   Entity type id.
   * @param \Throwable|null $previous
   *   Previous exception.
   */
  public function __construct($entityProperties, $entityTypeId, Throwable $previous = NULL) {
    parent::__construct($this->t('Entity not found. type: %type properties: %props', [
      '%type' => $entityTypeId,
      '%props' => var_export($entityProperties, TRUE),
    ]), 0, $previous);
    $this->entityProperties = $entityProperties;
    $this->entityTypeId = $entityTypeId;
  }

  /**
   * Getter.
   *
   * @return mixed
   *   The entityProperties.
   */
  public function getEntityProperties() {
    return $this->entityProperties;
  }

  /**
   * Getter.
   *
   * @return string
   *   The entityTypeId.
   */
  public function getEntityTypeId() {
    return $this->entityTypeId;
  }

  /**
   * Get a formattable message.
   *
   * @return \Drupal\Component\Render\FormattableMarkup
   *   The message.
   */
  public function getFormattableMessage() {
    return new FormattableMarkup('Entity not found. type: %type properties: %props', [
      '%type' => $this->entityTypeId,
      '%props' => var_export($this->entityProperties, TRUE),
    ]);
  }

}
