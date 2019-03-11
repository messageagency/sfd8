<?php

namespace Drupal\salesforce_mapping_ui\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Event\SalesforceNoticeEvent;
use Drupal\salesforce\Event\SalesforceEvents;

/**
 * Provides a form for deleting a salesforce_mapped_oject entity.
 *
 * @ingroup content_entity_example
 */
class MappedObjectDeleteForm extends ContentEntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete this mapped object?');
  }

  /**
   * {@inheritdoc}
   *
   * If the delete command is canceled, return to the contact list.
   */
  public function getCancelUrl() {
    return $this->getEntity()->toUrl();
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   *
   * Delete the entity and log the event. Event dispatcher service sends
   * Salesforce notvie level event which logs notice.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mapped_object = $this->getEntity();
    $form_state->setRedirectUrl($mapped_object->getMappedEntity()->toUrl());
    $message = 'MappedObject @sfid deleted.';
    $args = ['@sfid' => $mapped_object->salesforce_id->value];
    \Drupal::service('event_dispatcher')->dispatch(SalesforceEvents::NOTICE, new SalesforceNoticeEvent(NULL, $message, $args));
    $mapped_object->delete();
  }

}
