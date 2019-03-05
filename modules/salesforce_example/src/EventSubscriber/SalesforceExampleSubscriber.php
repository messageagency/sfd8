<?php

namespace Drupal\salesforce_example\EventSubscriber;

use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce_mapping\Event\SalesforcePullEvent;
use Drupal\salesforce_mapping\Event\SalesforcePushOpEvent;
use Drupal\salesforce_mapping\Event\SalesforcePushAllowedEvent;
use Drupal\salesforce_mapping\Event\SalesforcePushParamsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\salesforce_mapping\Event\SalesforceQueryEvent;

/**
 * Class SalesforceExampleSubscriber.
 *
 * Trivial example of subscribing to salesforce.push_params event to set a
 * constant value for Contact.FirstName.
 *
 * @package Drupal\salesforce_example
 */
class SalesforceExampleSubscriber implements EventSubscriberInterface {

  /**
   * SalesforcePushAllowedEvent callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePushAllowedEvent $event
   *   The push allowed event.
   */
  public function pushAllowed(SalesforcePushAllowedEvent $event) {
    /** @var \Drupal\Core\Entity\Entity $entity */
    $entity = $event->getEntity();
    if ($entity && $entity->getEntityTypeId() == 'unpushable_entity') {
      $event->disallowPush();
    }
  }

  /**
   * SalesforcePushParamsEvent callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePushParamsEvent $event
   *   The event.
   */
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

  /**
   * SalesforcePushParamsEvent push success callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePushParamsEvent $event
   *   The event.
   */
  public function pushSuccess(SalesforcePushParamsEvent $event) {
    switch ($event->getMappedObject()->getMapping()->id()) {
      case 'mapping1':
        // Do X.
        break;

      case 'mapping2':
        // Do Y.
        break;
    }
    \Drupal::messenger()->addStatus('push success example subscriber!: ' . $event->getMappedObject()->sfid());
  }

  /**
   * SalesforcePushParamsEvent push fail callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePushOpEvent $event
   *   The event.
   */
  public function pushFail(SalesforcePushOpEvent $event) {
    \Drupal::messenger()->addStatus('push fail example: ' . $event->getMappedObject()->id());
  }

  /**
   * SalesforceQueryEvent pull query alter event callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforceQueryEvent $event
   *   The event.
   */
  public function pullQueryAlter(SalesforceQueryEvent $event) {
    $mapping = $event->getMapping();
    switch ($mapping->id()) {
      case 'contact':
        // Add attachments to the Contact pull mapping so that we can save
        // profile pics. See also ::pullPresave.
        $query = $event->getQuery();
        // Add a subquery:
        $query->fields[] = "(SELECT Id FROM Attachments WHERE Name = 'example.jpg' LIMIT 1)";
        // Add a field from lookup:
        $query->fields[] = "Account.Name";
        // Add a condition:
        $query->addCondition('Email', "''", '!=');
        // Add a limit:
        $query->limit = 5;
        break;
    }
  }

  /**
   * Pull presave event callback.
   *
   * @param \Drupal\salesforce_mapping\Event\SalesforcePullEvent $event
   *   The event.
   */
  public function pullPresave(SalesforcePullEvent $event) {
    $mapping = $event->getMapping();
    switch ($mapping->id()) {
      case 'contact':
        // In this example, given a Contact record, do a just-in-time fetch for
        // Attachment data, if given.
        $account = $event->getEntity();
        $sf_data = $event->getMappedObject()->getSalesforceRecord();
        $client = \Drupal::service('salesforce.client');
        // Fetch the attachment URL from raw sf data.
        $attachments = [];
        try {
          $attachments = $sf_data->field('Attachments');
        }
        catch (\Exception $e) {
          // noop, fall through.
        }
        if (@$attachments['totalSize'] < 1) {
          // If Attachments field was empty, do nothing.
          return;
        }
        // If Attachments field was set, it will contain a URL from which we can
        // fetch the attached binary. We must append "body" to the retreived URL
        // https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/dome_sobject_blob_retrieve.htm
        $attachment_url = $attachments['records'][0]['attributes']['url'];
        $attachment_url = $client->getInstanceUrl() . $attachment_url . '/Body';

        // Fetch the attachment body, via RestClient::httpRequestRaw.
        try {
          $file_data = $client->httpRequestRaw($attachment_url);
        }
        catch (\Exception $e) {
          // Unable to fetch file data from SF.
          \Drupal::logger('db')->error(t('failed to fetch attachment for user @user', ['@user' => $account->id()]));
          return;
        }

        // Fetch file destination from account settings.
        $destination = "public://user_picture/profilepic-" . $sf_data->id() . ".jpg";

        // Attach the new file id to the user entity
        /* var \Drupal\file\FileInterface */
        if ($file = file_save_data($file_data, $destination, FILE_EXISTS_REPLACE)) {
          $account->user_picture->target_id = $file->id();
        }
        else {
          \Drupal::logger('db')->error('failed to save profile pic for user ' . $account->id());
        }

        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      SalesforceEvents::PUSH_ALLOWED => 'pushAllowed',
      SalesforceEvents::PUSH_PARAMS => 'pushParamsAlter',
      SalesforceEvents::PUSH_SUCCESS => 'pushSuccess',
      SalesforceEvents::PUSH_FAIL => 'pushFail',
      SalesforceEvents::PULL_PRESAVE => 'pullPresave',
      SalesforceEvents::PULL_QUERY => 'pullQueryAlter',
    ];
    return $events;
  }

}
