<?php

namespace Drupal\salesforce_mapping_ui\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Salesforce Mapping Disable Form .
 */
class SalesforceMappingDisableForm extends EntityConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to disable the mapping %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('Disabling a mapping will stop any automatic synchronization and hide the mapping.');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.salesforce_mapping.list');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Disable');
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array $form, FormStateInterface $form_state) {
    parent::submit($form, $form_state);

    $this->entity->disable()->save();
    $form_state['redirect_route'] = [
      'route_name' => 'entity.salesforce_mapping.list',
    ];
  }

}
