<?php

namespace Drupal\salesforce_example\EventSubscriber;

use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce_mapping\Event\SalesforcePushOpEvent;
use Drupal\salesforce_mapping\Event\SalesforcePushParamsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\salesforce\Exception;


/**
 * Class SalesforceExampleSubscriber.
 * Trivial example of subscribing to salesforce.push_params event to set a
 * constant value for Contact.FirstName
 *
 * @package Drupal\salesforce_example
 */
class SalesforceExampleSubscriber implements EventSubscriberInterface {

  public function pushAllowed(SalesforcePushOpEvent $event) {
    /** @var \Drupal\Core\Entity\Entity $entity */
    $entity = $event->getEntity();
    if ($entity && $entity->getEntityTypeId() == 'unpushable_entity') {
      throw new Exception('Prevent push of Unpushable Entity');
    }
  }

  public function pushParamsAlter(SalesforcePushParamsEvent $event) {
    $mapping = $event->getMapping();
    $mapped_object = $event->getMappedObject();
    $params = $event->getParams();

    /** @var \Drupal\Core\Entity\Entity $entity */
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

  public function pushSuccess(SalesforcePushParamsEvent $event) {
    switch ($event->getMappedObject()->getMapping()->id()) {
      case 'mapping1':
        // do X
        break;
      case 'mapping2':
        // do Y
        break;
    }
    drupal_set_message('push success example subscriber!: ' . $event->getMappedObject()->sfid());
  }

  public function pushFail(SalesforcePushOpEvent $event) {
    drupal_set_message('push fail example: ' . $event->getMappedObject()->id());
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events = [
      SalesforceEvents::PUSH_ALLOWED => 'pushAllowed',
      SalesforceEvents::PUSH_PARAMS => 'pushParamsAlter',
      SalesforceEvents::PUSH_SUCCESS => 'pushSuccess',
      SalesforceEvents::PUSH_FAIL => 'pushFail',
    ];
    return $events;
  }

}
