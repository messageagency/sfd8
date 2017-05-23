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
  public function __construct(ResponseInterface $response = NULL, $message = "", $code = 0, \Exception $previous = NULL) {
    $this->response = $response;
    $message .= $this->getResponseBody();
    parent::__construct($message, $code, $previous);
  }

  public function getResponse() {
    return $this->response;
  }

  public function getResponseBody() {
    if (!$this->response) {
      return;
    }
    $body = $this->response->getBody();
    if ($body) {
      return $body->getContents();
    }
    return '';
  }

}
