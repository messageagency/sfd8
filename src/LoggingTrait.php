<?php

namespace Drupal\salesforce;

/**
 * Provides a trait for Drupal logger wrapper method.
 */
trait LoggingTrait {

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
   public function log($name, $level, $message, array $placeholders = []) {
     if (!empty($placeholders)) {
       \Drupal::logger($name)->$level($message, $placeholders);
     }
     else {
       \Drupal::logger($name)->$level($message);
     }
   }
}
