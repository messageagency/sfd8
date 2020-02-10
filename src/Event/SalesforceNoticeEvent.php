<?php

namespace Drupal\salesforce\Event;

use Drupal\Core\Logger\RfcLogLevel;

/**
 * Notice event.
 */
class SalesforceNoticeEvent extends SalesforceExceptionEvent {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Throwable $e = NULL, $message = '', array $args = []) {
    parent::__construct(RfcLogLevel::NOTICE, $e, $message, $args);
  }

}
