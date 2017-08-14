<?php

namespace Drupal\salesforce_mapping;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the mapped object schema handler in order to add some unique keys.
 */
class MappedObjectStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);
    // backwards compatibility for salesforce_mapping_update_8001
    // key is too long if length is 255, so we have to wait until the db update
    // fires to avoid WSOD
    $version = drupal_get_installed_schema_version('salesforce_mapping');
    if ($version < 8001 || $version >= 8003) {
      $schema['salesforce_mapped_object']['unique keys'] += array(
        'entity__mapping' => array('entity_type_id', 'salesforce_mapping', 'entity_id'),
      );
    }
    $schema['salesforce_mapped_object']['fields']['entity_type_id']['length'] =
    $schema['salesforce_mapped_object']['fields']['salesforce_mapping']['length'] =
    $schema['salesforce_mapped_object_revision']['fields']['entity_type_id']['length'] =
    $schema['salesforce_mapped_object_revision']['fields']['salesforce_mapping']['length'] =
      EntityTypeInterface::ID_MAX_LENGTH;

    return $schema;
  }
}
