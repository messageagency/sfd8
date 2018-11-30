<?php

namespace Drupal\salesforce_mapping\Plugin\Field\FieldType;

use Drupal\salesforce_mapping\Plugin\Field\CalculatedLinkItemBase;

/**
 * Lifted from https://www.drupal.org/docs/8/api/entity-api/dynamicvirtual-field-values-using-computed-field-property-classes.
 *
 * @FieldType(
 *   id = "salesforce_link",
 *   label = @Translation("Salesforce Record"),
 *   description = @Translation("A link to the salesforce record."),
 *   default_widget = "link_default",
 *   default_formatter = "link",
 *   constraints = {
 *     "LinkType" = {},
 *     "LinkAccess" = {},
 *     "LinkExternalProtocols" = {},
 *     "LinkNotExistingInternal" = {}
 *   }
 * )
 */
class SalesforceLinkItem extends CalculatedLinkItemBase {

  /**
   * Calculates the value of the field and sets it.
   */
  protected function ensureCalculated() {
    if (!$this->isCalculated) {
      $entity = $this->getEntity();
      if (!$entity->isNew()) {
        $value = [
          'uri' => $entity->getSalesforceUrl(),
          'title' => $entity->sfid(),
        ];
        $this->setValue($value);
      }
      $this->isCalculated = TRUE;
    }
  }

}
