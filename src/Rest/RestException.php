<?php

namespace Drupal\salesforce\Rest;

use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class RestException.
 *
 * @package Drupal\salesforce\Rest
 */
class RestException extends \RuntimeException implements ExceptionInterface {

  protected $response;

  /**
   * RestException constructor.
   *
   * @param \Psr\Http\Message\ResponseInterface|NULL $response
   * @param string $message
   * @param int $code
   * @param \Exception|NULL $previous
   */
  public function __construct(ResponseInterface $response = NULL, $message = "", $code = 0, \Exception $previous = NULL) {
    $this->response = $response;
    $message .= $this->getResponseBody();
    parent::__construct($message, $code, $previous);
  }

  /**
   * @return NULL|\Psr\Http\Message\ResponseInterface
   */
  public function getResponse() {
    return $this->response;
  }

  /**
   * @return string|null
   */
  public function getResponseBody() {
    if (!$this->response) {
      return NULL;
    }
    $body = $this->response->getBody();
    if ($body) {
      return $body->getContents();
    }
    return '';
  }

}
