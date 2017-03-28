<?php

namespace Drupal\salesforce\Event;

use Drupal\Core\Logger\RfcLogLevel;

/**
 *
 */
class SalesforceErrorEvent extends SalesforceExceptionEvent {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Exception $e = NULL, $message = '', array $args = []) {
    parent::__construct(RfcLogLevel::ERROR, $e, $message, $args);
  }

}
