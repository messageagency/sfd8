<?php

/**
 * @file
 * Contains Drupal\salesforce_pull\Plugin\QueueWorker\PullBase.php
 */

namespace Drupal\Tests\salesforce\salesforce_pull;

use Drupal\salesforce_pull\Plugin\QueueWorker\PullBase;

/**
 * Provides base functionality for the Salesforce Pull Queue Workers.
 */
class TestPullBase extends PullBase {

  /**
   * Overrides parent::loadMapping
   * Wrapper for salesforce_mapping_load();
   */
  protected function loadMapping($id) {
    // return mapping object
    return $this->etm->getStorage('salesforce_mapping')->load($id);
  }

  /**
   * Overrides parent::loadMappingObjects
   * Wrapper for salesforce_mapped_object_load_multiple();
   */
  protected function loadMappingObjects(array $properties) {
    // return a mapped object
    return $this->etm
      ->getStorage('salesforce_mapped_object')
      ->loadByProperties($properties);
  }

  /**
   * Overrides parent::watchdogException
   * Wrapper for watchdog_exception()
   */
  protected function watchdogException(\Exception $e) {
  }

  /**
   * Ovverides parent::log()
   */
  protected function log($name, $level, $message, array $placeholders = []) {
  }
}
