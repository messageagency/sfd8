<?php

namespace Drupal\salesforce_mapping\Plugin\Field;

use Drupal\Core\Field\FieldItemList;

/**
 * Lifted from https://www.drupal.org/docs/8/api/entity-api/dynamicvirtual-field-values-using-computed-field-property-classes.
 *
 * @deprecated ComputedItemList is deprecated and will be removed in 8.x-4.0. Use \Drupal\salesforce_mapping\Plugin\Field\FieldType\SalesforceLinkItemList
 */
class ComputedItemList extends FieldItemList {

  /**
   * {@inheritdoc}
   */
  public function getIterator() {
    $this->ensurePopulated();
    return new \ArrayIterator($this->list);
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $this->ensurePopulated();
    return parent::getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    $this->ensurePopulated();
    return parent::isEmpty();
  }

  /**
   * Makes sure that the item list is never empty.
   *
   * For 'normal' fields that use database storage the field item list is
   * initially empty, but since this is a computed field this always has a
   * value.
   * Make sure the item list is always populated, so this field is not skipped
   * for rendering in EntityViewDisplay and friends.
   *
   * @todo This will no longer be necessary once #2392845 is fixed.
   *
   * @see https://www.drupal.org/node/2392845
   */
  protected function ensurePopulated() {
    if (!isset($this->list[0])) {
      $this->list[0] = $this->createItem(0);
    }
  }

}
