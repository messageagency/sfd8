<?php

namespace Drupal\salesforce\Event;

use Drupal\Core\Logger\RfcLogLevel;

/**
 * Error event.
 */
class SalesforceErrorEvent extends SalesforceExceptionEvent {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Throwable $e = NULL, $message = '', array $args = []) {
    parent::__construct(RfcLogLevel::ERROR, $e, $message, $args);
  }

}
