<?php


/**
 * @file
 * Contains Drupal\salesforce_mapping\Form\MappedObjectDeleteForm
 */

namespace Drupal\salesforce_mapping\Form;

use Drupal\Core\Entity\ContentEntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

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
    $mapped_object = $this->getEntity();
    $entity = $mapped_object->getMappedEntity();
    return $entity->toUrl('salesforce');
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
   * Delete the entity and log the event. logger() replaces the watchdog.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mapped_object = $this->getEntity();
    $form_state->setRedirect($mapped_object->getMappedEntity()->toUrl('salesforce'));
    $this
      ->logger('salesforce_mapped_oject')
      ->notice('MappedObject @sfid deleted.', array(
        '@sfid' => $mapped_object->salesforce_id->value
      ));
    $mapped_object->delete();
  }

}
