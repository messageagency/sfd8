<?php

namespace Drupal\salesforce_logger\EventSubscriber;

use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Utility\Error;
use Drupal\salesforce\Event\SalesforceEvents;
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

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Create a new Salesforce Logger Subscriber.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
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

  /**
   * SalesforceException event callback.
   *
   * @param \Drupal\salesforce\Event\SalesforceExceptionEventInterface $event
   *   The event.
   */
  public function salesforceException(SalesforceExceptionEventInterface $event) {
    $log_level_setting = \Drupal::configFactory()->get('salesforce_logger.settings')->get('log_level');
    $event_level = $event->getLevel();
    // Only log events whose log level is greater or equal to min log level
    // setting.
    if ($log_level_setting != SalesforceEvents::NOTICE) {
      if ($log_level_setting == SalesforceEvents::ERROR && $event_level != RfcLogLevel::ERROR) {
        return;
      }
      if ($log_level_setting == SalesforceEvents::WARNING && $event_level == RfcLogLevel::NOTICE) {
        return;
      }
    }

    $exception = $event->getException();
    if ($exception) {
      $this->logger->log($event->getLevel(), self::EXCEPTION_MESSAGE_PLACEHOLDER, Error::decodeException($exception));
    }
    else {
      $this->logger->log($event->getLevel(), $event->getMessage(), $event->getContext());
    }
  }

}
