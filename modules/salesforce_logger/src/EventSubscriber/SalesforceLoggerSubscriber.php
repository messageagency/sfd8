<?php

namespace Drupal\salesforce_logger\EventSubscriber;

use Drupal\Core\Entity\Entity;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\SalesforceExceptionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\salesforce\Exception;


/**
 * Class SalesforceLoggerSubscriber.
 *
 * @package Drupal\salesforce_logger
 */
class SalesforceLoggerSubscriber implements EventSubscriberInterface {

  public function __construct() {
    
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      SalesforceEvents::EXCEPTION => 'salesforceException',
    ];
    return $events;
  }

  public function salesforceException(SalesforceExceptionEvent $event) {
    
  }

}
