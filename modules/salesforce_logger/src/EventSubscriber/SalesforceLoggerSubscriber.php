<?php

namespace Drupal\salesforce_logger\EventSubscriber;

use Drupal\Core\Utility\Error;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Exception;
use Drupal\salesforce\Event\SalesforceExceptionEventInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class SalesforceLoggerSubscriber.
 *
 * @package Drupal\salesforce_logger
 */
class SalesforceLoggerSubscriber implements EventSubscriberInterface {

  const EXCEPTION_MESSAGE_PLACEHOLDER = '%type: @message in %function (line %line of %file).';

  protected $logger;

  /**
   * Create a new Salesforce Logger Subscriber.
   *
   * @param LoggerChannelFactoryInterface $logger_factory
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      SalesforceEvents::ERROR => 'salesforceException',
      SalesforceEvents::WARNING => 'salesforceException',
      SalesforceEvents::NOTICE => 'salesforceException',
    ];
    return $events;
  }

  public function salesforceException(SalesforceExceptionEventInterface $event) {
    // @TODO configure log levels; only log if configured level >= error level
    $exception = $event->getException();

    if ($exception) {
      $this->logger->log($event->getLevel(), self::EXCEPTION_MESSAGE_PLACEHOLDER, Error::decodeException($exception));
    }

    $this->logger->log($event->getLevel(), $event->getMessage(), $event->getContext());
  }

}
