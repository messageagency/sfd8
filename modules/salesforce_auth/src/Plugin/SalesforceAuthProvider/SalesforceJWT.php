<?php

namespace Drupal\salesforce_auth\Plugin\SalesforceAuthProvider;

use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_auth\Consumer\JWTCredentials;
use Drupal\salesforce_auth\SalesforceAuthProviderBase;
use Drupal\salesforce_auth\SalesforceAuthProviderPluginInterface;
use Drupal\salesforce_auth\Service\SalesforceJWT as SalesforceJWTService;
use Drupal\salesforce_auth\Storage\TokenStorage;
use OAuth\Common\Http\Client\CurlClient;

/**
 * @SalesforceAuthProvider(
 *   id = "jwt",
 *   label = @Translation("Salesforce JWT OAuth")
 * )
 */
class SalesforceJWT extends SalesforceAuthProviderBase implements SalesforceAuthProviderPluginInterface  {

  /**
   * The auth provider service.
   * @var \Drupal\salesforce_auth\Service\SalesforceJWT
   */
  protected $service;

  /**
   * Service wrapper.
   *
   * @return \Drupal\salesforce_auth\Service\SalesforceJWT
   *   The auth service.
   */
  public function service() {
    if (!$this->service) {
      $cred = new JWTCredentials($this->configuration['consumer_key'], $this->configuration['login_url'], $this->configuration['login_user'], $this->configuration['encrypt_key']);
      $this->service = new SalesforceJWTService($cred, \Drupal::service('salesforce_auth.http_client_wrapper'), \Drupal::service('salesforce_auth.token_storage'));
    }
    return $this->service;
  }

  public function getLoginUrl() {
    return $this->getConfiguration('login_url');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['consumer_key'] = [
      '#title' => t('Salesforce consumer key'),
      '#type' => 'textfield',
      '#description' => t('Consumer key of the Salesforce remote application you want to grant access to'),
      '#required' => TRUE,
      '#default_value' => $this->getConfiguration('consumer_key'),
    ];

    $form['login_user'] = [
      '#title' => $this->t('Salesforce login user'),
      '#type' => 'textfield',
      '#description' => $this->t('User account to issue token to'),
      '#required' => TRUE,
      '#default_value' => $this->getConfiguration('login_user'),
    ];

    $form['login_url'] = [
      '#title' => t('Login URL'),
      '#type' => 'textfield',
      '#default_value' => $this->getConfiguration('login_url') ?: 'https://test.salesforce.com',
      '#description' => t('Enter a login URL, either https://login.salesforce.com or https://test.salesforce.com.'),
      '#required' => TRUE,
    ];

    // Can't use key-select here because its #process method is not firing on ajax, and the list is empty. DERP.
    $form['encrypt_key'] = [
      '#title' => 'Private Key',
      '#type' => 'select',
      '#options' => \Drupal::service('key.repository')->getKeyNamesAsOptions(['type' => 'authentication']),
      '#required' => TRUE,
      '#default_value' => $this->getConfiguration('encrypt_key'),
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