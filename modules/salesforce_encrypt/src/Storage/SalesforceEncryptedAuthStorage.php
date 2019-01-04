<?php

namespace Drupal\salesforce_encrypt\Storage;

use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityMalformedException;

class SalesforceEncryptedAuthStorage extends ConfigEntityStorage {

  /**
   * Implements Drupal\Core\Entity\EntityStorageInterface::save().
   *
   * @throws EntityMalformedException
   *   When attempting to save a configuration entity that has no ID.
   */
  public function save(EntityInterface $entity) {
    // Encrypt the sensitive values and hand off to parent.
    return parent::save($entity);
  }

  protected function doLoadMultiple(array $ids = NULL) {
    $entities = parent::doLoadMultiple($ids);
    // Decrypt the sensitive values and return
    return $entities;
  }


}