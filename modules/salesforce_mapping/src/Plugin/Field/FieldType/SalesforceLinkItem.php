<?php

namespace Drupal\salesforce_mapping\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\link\Plugin\Field\FieldType\LinkItem;

/**
 * Salesforce link to external record.
 *
 * @FieldType(
 *   id = "salesforce_link",
 *   label = @Translation("Salesforce Record"),
 *   description = @Translation("A link to the salesforce record."),
 *   default_widget = "link_default",
 *   default_formatter = "link",
 *   list_class = "\Drupal\salesforce_mapping\Plugin\Field\FieldType\SalesforceLinkItemList",
 *   constraints = {
 *     "LinkType" = {},
 *     "LinkAccess" = {},
 *     "LinkExternalProtocols" = {},
 *     "LinkNotExistingInternal" = {}
 *   }
 * )
 */
class SalesforceLinkItem extends LinkItem {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['uri'] = DataDefinition::create('uri')
      ->setLabel(t('Salesforce URL'));
    $properties['title'] = DataDefinition::create('string')
      ->setLabel(t('Salesforce ID'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return $this->getEntity()->isNew() || !method_exists($this->getEntity(), 'sfid') || !$this->getEntity()->sfid();
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [];
  }

}
