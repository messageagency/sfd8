<?php

namespace Drupal\salesforce_mapping\Tests;

use Drupal\salesforce_mapping\MappedObjectList;
use Drupal\Core\Entity\EntityInterface;

class TestMappedObjectList extends MappedObjectList {
  public function buildOperations(EntityInterface $entity) {
    return array();
  }
}