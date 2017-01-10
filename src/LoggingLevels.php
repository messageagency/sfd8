<?php

namespace Drupal\salesforce;

/**
 * Defines events for Salesforce.
 *
 * @see \Drupal\salesforce\SalesforceEvent
 */
final class LoggingLevels {

  /**
   * Name of the logging level method using in Drupal::logger.
   *
   * Restricts list to valid levels
   *
   * @Level
   *
   * @var string
   */
  const EMERGENCY = 'emergency';
  const ALERT = 'alert';
  const CRITIAL = 'critical';
  const ERROR = 'error';
  const WARNING = 'warning';
  const NOTICE = 'notice';
  const INFO = 'info';
  const DEBUG = 'debug';  
}
