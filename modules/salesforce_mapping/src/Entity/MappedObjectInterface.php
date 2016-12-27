<?php

namespace Drupal\salesforce_mapping\Entity;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\RevisionLogInterface;

/**
 *
 */
interface MappedObjectInterface extends EntityChangedInterface, RevisionLogInterface {
  // Placeholder interface.
  // @TODO figure out what to abstract out of MappedObject

  const SFID_MAX_LENGTH = 18;

}
