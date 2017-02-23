<?php

namespace Drupal\salesforce_example\EventSubscriber;

use Drupal\salesforce\SalesforceEvents;
use Drupal\salesforce_mapping\SalesforcePushEvent;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class SalesforceExampleSubscriber.
 * Trivial example of subscribing to salesforce.push_params event to set a
 * constant value for Contact.FirstName
 *
 * @package Drupal\salesforce_example
 */
class SalesforceExampleSubscriber implements EventSubscriberInterface {

  public function pushAllowed(SalesforcePushOpEvent $event) {
    $entity = $event->getEntity();
    if ($entity->getEntityTypeId() == 'unpushable_entity') {
      throw new Exception('Prevent push of Unpushable Entity');
    }
  }

  public function pushParamsAlter(SalesforcePushParamsEvent $event) {
    $mapping = $event->getMapping();
    $mapped_object = $event->getMappedObject();
    $params = $event->getParams();
    $entity = $event->getEntity();
    if ($entity->getEntityTypeId() != 'user') {
      return;
    }
    if ($mapping->id() != 'salesforce_example_contact') {
      return;
    }
    if ($mapped_object->isNew()) {
      return;
    }
    $params->setParam('FirstName', 'SalesforceExample');
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events = [
      SalesforceEvents::PUSH_ALLOWED => 'pushAllowed',
      SalesforceEvents::PUSH_PARAMS => 'pushParamsAlter',
    ];
    return $events;
  }

}
