<?php

namespace Drupal\salesforce_auth\Plugin;

use Drupal\Core\Form\FormStateInterface;

/**
 * @SalesforceAuthProvider(
 *   id = "jwt",
 *   label = @Translation("Salesforce JWT OAuth")
 * )
 */
class SalesforceJWT extends SalesforceBase implements SalesforceAuthProviderPluginInterface  {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['consumer_key'] = [
      '#title' => t('Salesforce consumer key'),
      '#type' => 'textfield',
      '#description' => t('Consumer key of the Salesforce remote application you want to grant access to'),
      '#required' => TRUE,
      '#default_value' => $this->getConfiguration()['consumer_key'],
    ];

    $form['login_user'] = [
      '#title' => $this->t('Salesforce login user'),
      '#type' => 'textfield',
      '#description' => $this->t('User account to issue token to'),
      '#required' => TRUE,
      '#default_value' => $this->getConfiguration()['login_user'],
    ];

    $form['encrypt_key'] = [
      '#title' => 'Private Key',
      '#type' => 'key_select',
      '#required' => TRUE,
      '#default_value' => $this->getConfiguration()['encrypt_key'],
    ];

    $form['login_url'] = [
      '#title' => t('Login URL'),
      '#type' => 'textfield',
      '#default_value' => $this->getConfiguration()['login_url'] ?: 'https://test.salesforce.com',
      '#description' => t('Enter a login URL, either https://login.salesforce.com or https://test.salesforce.com.'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement validateConfigurationForm() method.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->setConfiguration($form_state->getValues());
  }

}