<?php

namespace Drupal\salesforce_logger\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Event\SalesforceEvents;

/**
 * Creates authorization form for Salesforce.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'salesforce_logger.settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'salesforce_logger.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('salesforce_logger.settings');

    $form['log_level'] = [
      '#title' => $this->t('Salesforce Logger log level'),
      '#type' => 'radios',
      '#options' => [
        SalesforceEvents::ERROR => $this->t('Log errors only'),
        SalesforceEvents::WARNING => $this->t('Log warnings and errors'),
        SalesforceEvents::NOTICE => $this->t('Log all salesforce events'),
      ],
      '#default_value' => $config->get('log_level'),
    ];

    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('salesforce_logger.settings');
    $config->set('log_level', $form_state->getValue('log_level'));
    $config->save();
    parent::submitForm($form, $form_state);
  }

}
