<?php

namespace Drupal\salesforce\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 *
 */
class SalesforceExceptionEvent extends SalesforceBaseEvent {

  protected $exception;

  /**
   * {@inheritdoc}
   */
  public function __construct(\Exception $e) {
    $this->exception = $e;
  }

  /**
   * @return \Exception
   *   The exception
   */
  public function getException() {
    return $this->exception;
  }

}
