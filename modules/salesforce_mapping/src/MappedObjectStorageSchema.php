<?php

namespace Drupal\salesforce_mapping;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Defines the mapped object schema handler in order to add some unique keys.
 */
class MappedObjectStorageSchema extends SqlContentEntityStorageSchema {
  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    $schema['salesforce_mapped_object']['unique keys'] += array(
      'entity__mapping' => array('entity_type_id', 'salesforce_mapping', 'entity_id'),
    );

    return $schema;
  }
}
