<?php

namespace Drupal\salesforce\Event;

use Symfony\Component\EventDispatcher\Event;
use Drupal\Component\Render\FormattableMarkup;

/**
 *
 */
abstract class SalesforceExceptionEvent extends SalesforceBaseEvent {

  protected $exception;
  protected $message;
  protected $args;
  protected $level;

  /**
   * {@inheritdoc}
   */
  public function __construct($level, \Exception $e = NULL, $message = '', array $args = []) {
    $this->exception = $e;
    $this->level = $level;
    $this->message = $message;
    $this->args = $args;
  }

  /**
   * @return \Exception
   *   The exception
   */
  public function getException() {
    return $this->exception;
  }

  /**
   * @return mixed Log Level
   *   Severity level for the event. Probably a Drupal\Core\Logger\RfcLogLevel 
   *   or Psr\Log\LogLevel value.
   */
  public function getLevel() {
    return $this->level;
  }

  /**
   * @return FormattableMarkup
   *   The formatted message for this event. (Note: to get the Exception
   *   message, use ::getException()->getMessage()).
   */
  public function getMessage() {
    return new FormattableMarkup($message, $args);
  }

}
