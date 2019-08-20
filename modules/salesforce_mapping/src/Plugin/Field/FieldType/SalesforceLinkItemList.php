<?php

namespace Drupal\salesforce_mapping\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Lifted from https://www.drupal.org/docs/8/api/entity-api/dynamicvirtual-field-values-using-computed-field-property-classes.
 */
class SalesforceLinkItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    $value = NULL;
    if (!$entity->isNew()) {
      $value = [
        'uri' => $entity->getSalesforceUrl(),
        'title' => $entity->sfid(),
      ];
      $this->setValue($value);
    }
    $this->list[0] = $this->createItem(0, $value);
  }

}
