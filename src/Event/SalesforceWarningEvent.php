<?php

namespace Drupal\salesforce\Event;

use Drupal\Core\Logger\RfcLogLevel;

/**
 * Warning event.
 */
class SalesforceWarningEvent extends SalesforceExceptionEvent {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Throwable $e = NULL, $message = '', array $args = []) {
    parent::__construct(RfcLogLevel::WARNING, $e, $message, $args);
  }

}
