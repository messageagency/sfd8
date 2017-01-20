<?php

namespace Drupal\salesforce;

use Psr\Log\LoggerTrait;

/**
 * Provides a trait for Drupal logger wrapper method.
 */
trait LoggingTrait {

  use LoggerTrait;

  /**
   * Wrapper method for logging, added for testability
   *
   * @param string
   *   Module name
   * @param string
   *   Severity level, see LoggingLevels
   * @param string
   *   message, with tokens is appropriate
   * @param array
   *   placeholders for tokens, if appriate
   */
   protected function log($name, $level, $message, array $placeholders = []) {
     if (empty($placeholders)) {
       $placeholders = [];
     }
     \Drupal::logger($name)->log($level, $message, $placeholders);
   }

   /**
    * Wrapper for watchdog_exception()
    */
   protected function watchdogException(\Exception $e) {
     watchdog_exception(__CLASS__, $e);
   }

}
