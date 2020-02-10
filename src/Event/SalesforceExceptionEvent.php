<?php

namespace Drupal\salesforce\Event;

/**
 * Base class for Salesforce Exception events, primarily for logging.
 */
abstract class SalesforceExceptionEvent extends SalesforceBaseEvent implements SalesforceExceptionEventInterface {

  /**
   * Exception.
   *
   * @var \Throwable|null
   */
  protected $exception;

  /**
   * Message for logging.
   *
   * @var string
   */
  protected $message;

  /**
   * Context, for t() translation.
   *
   * @var array
   */
  protected $context;

  /**
   * Event level: notice, warning, or error.
   *
   * @var string
   */
  protected $level;

  /**
   * SalesforceExceptionEvent constructor.
   *
   * @param string $level
   *   Values are RfcLogLevel::NOTICE, RfcLogLevel::WARNING, RfcLogLevel::ERROR.
   * @param \Throwable|null $e
   *   A related Exception, if available.
   * @param string $message
   *   The translatable message string, suitable for t().
   * @param array $context
   *   Parameter array suitable for t(), to be translated for $message.
   */
  public function __construct($level, \Throwable $e = NULL, $message = '', array $context = []) {
    $this->exception = $e;
    $this->level = $level;
    $this->message = $message;
    $this->context = $context;
  }

  /**
   * {@inheritdoc}
   */
  public function getException() {
    return $this->exception;
  }

  /**
   * {@inheritdoc}
   */
  public function getLevel() {
    return $this->level;
  }

  /**
   * {@inheritdoc}
   */
  public function getMessage() {
    if ($this->message) {
      return $this->message;
    }
    elseif ($this->exception && $this->exception->getMessage()) {
      return $this->exception->getMessage();
    }
    else {
      return 'Unknown Salesforce event.';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return $this->context;
  }

}
