<?php

namespace Drupal\salesforce;

use Symfony\Component\Serializer\Exception\Exception as SymfonyException;

/**
 *
 */
class RestException extends \RuntimeException implements SymfonyException {

  protected $response;

  /**
   *
   */
  public function __construct(RestResponse $response, $message = "", $code = 0, Throwable $previous = NULL) {
    parent::__construct($message, $code, $previous);
    $this->response = $response;
  }

}
