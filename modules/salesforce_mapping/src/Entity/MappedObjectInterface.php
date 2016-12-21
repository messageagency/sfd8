<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Entity\MappedObjectInterface.
 */

namespace Drupal\salesforce_mapping\Entity;

use Drupal\Core\Entity\EntityChangedInterface;

interface MappedObjectInterface extends EntityChangedInterface {
  // Placeholder interface.
  // @TODO figure out what to abstract out of MappedObject

  const SFID_MAX_LENGTH = 18;
  
}
