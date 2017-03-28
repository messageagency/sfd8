<?php

namespace Drupal\salesforce\Event;


/**
 *
 */
interface SalesforceExceptionEventInterface {

  /**
   * @return \Exception | NULL
   *   The exception or NULL if no exception was given.
   */
  public function getException();

  /**
   * @return mixed Log Level
   *   Severity level for the event. Probably a Drupal\Core\Logger\RfcLogLevel 
   *   or Psr\Log\LogLevel value.
   */
  public function getLevel();

  /**
   * @return string
   *   The formatted message for this event. (Note: to get the Exception
   *   message, use ::getExceptionMessage()). If no message was given, 
   *   FormattableMarkup will be an empty string.
   */
  public function getMessage();

  /**
   * @return array
   *   The context aka args for this message, suitable for passing to ::log
   */
  public function getContext();

}
