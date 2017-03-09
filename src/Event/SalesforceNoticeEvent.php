<?php

namespace Drupal\salesforce\Event;

use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Logger\RfcLogLevel;

/**
 *
 */
class SalesforceNoticeEvent extends SalesforceExceptionEvent {

  /**
   * {@inheritdoc}
   */
  public function __construct(\Exception $e = NULL, $message = '', array $args = []) {
    parent::__construct(RfcLogLevel::NOTICE, $e, $message, $args);
  }

}
