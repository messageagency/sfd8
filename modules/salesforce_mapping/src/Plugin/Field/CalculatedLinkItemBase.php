<?php

namespace Drupal\salesforce_mapping\Plugin\Field;

use Drupal\link\Plugin\Field\FieldType\LinkItem;

/**
 * Calculated link item.
 *
 * @deprecated CalculatedLinkItemBase is deprecated and will be removed in 8.x-4.0.
 */
abstract class CalculatedLinkItemBase extends LinkItem {

  /**
   * Whether or not the value has been calculated.
   *
   * @var bool
   */
  protected $isCalculated = FALSE;

  /**
   * {@inheritdoc}
   */
  public function __get($name) {
    $this->ensureCalculated();
    return parent::__get($name);
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $this->ensureCalculated();
    return parent::isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $this->ensureCalculated();
    return parent::getValue();
  }

}
