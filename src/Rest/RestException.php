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
    $this->response = $response;
    $message .= $this->getResponseBody();
    parent::__construct($message, $code, $previous);
  }

  public function getResponse() {
    return $this->response;
  }

  public function getResponseBody() {
    $body = $this->response->getBody();
    if ($body) {
      return $body->getContents();
    }
    return '';
  }

}
