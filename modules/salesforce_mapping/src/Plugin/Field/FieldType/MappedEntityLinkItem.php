<?php 

namespace Drupal\salesforce_mapping\Plugin\Field\FieldType;

use Drupal\salesforce_mapping\Plugin\Field\CalculatedLinkItemBase;

/**
 * Lifted from https://www.drupal.org/docs/8/api/entity-api/dynamicvirtual-field-values-using-computed-field-property-classes
 *
 * @FieldType(
 *   id = "mapped_entity_link",
 *   label = @Translation("Mapped Entity"),
 *   description = @Translation("A link to mapped entity."),
 *   default_widget = "link_default",
 *   default_formatter = "link",
 *   constraints = {"LinkType" = {}, "LinkAccess" = {}, "LinkExternalProtocols" = {}, "LinkNotExistingInternal" = {}}
 * )
 */
class MappedEntityLinkItem extends CalculatedLinkItemBase {

  /**
   * Calculates the value of the field and sets it.
   */
  protected function ensureCalculated() {
    if (!$this->isCalculated) {
      $entity = $this->getEntity();
      if (!$entity->isNew()) {
        $mapped_entity = $entity->getMappedEntity();
        $value = [
          'uri' => $mapped_entity->toUrl()->toUriString(),
          'title' => $mapped_entity->label()
        ];
        $this->setValue($value);
      }
      $this->isCalculated = TRUE;
    }
  }

}