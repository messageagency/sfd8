<?php

namespace Drupal\salesforce\Event;


/**
 *
 */
abstract class SalesforceExceptionEvent extends SalesforceBaseEvent implements SalesforceExceptionEventInterface {

  protected $exception;
  protected $message;
  protected $context;
  protected $level;

  /**
   * {@inheritdoc}
   */
  public function __construct($level, \Exception $e = NULL, $message = '', array $context = []) {
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
    return $this->message;
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return $this->context;
  }

}
