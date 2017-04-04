<?php

namespace Drupal\salesforce\Rest;

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Psr\Http\Message\ResponseInterface;

/**
 *
 */
class RestException extends \RuntimeException implements ExceptionInterface {

  protected $response;

  /**
   *
   */
  public function __construct(ResponseInterface $response, $message = "", $code = 0, Throwable $previous = NULL) {
    parent::__construct($message, $code, $previous);
    $this->response = $response;
  }

}
