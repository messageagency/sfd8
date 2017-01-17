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
