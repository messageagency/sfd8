<?php

namespace Drupal\salesforce_auth\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Event\SalesforceNoticeEvent;
use Drupal\salesforce_auth\SalesforceAuth;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SalesforceAuthConfig extends ConfigFormBase {

  protected $salesforceAuth;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory, SalesforceAuth $salesforceAuth) {
    parent::__construct($config_factory);
    $this->salesforceAuth = $salesforceAuth;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('salesforce_auth')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'salesforce_auth_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['salesforce_auth.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!$this->salesforceAuth->hasProviders()) {
      return ['#markup'=> 'No auth providers have been enabled. Please enable an auth provider and create an auth config before continuing.'];
    }
    $config = $this->config('salesforce_auth.settings');
    $form = parent::buildForm($form, $form_state);
    $options = [];
    /** @var \Drupal\salesforce_auth\AuthProviderInterface $provider */
    foreach(\Drupal::service('salesforce_auth')->getProviders() as $provider) {
      /** @var \Drupal\salesforce_auth\Entity\AuthConfigInterface $conf */
      foreach ($provider->getConfigs() as $conf) {
        $options[$provider->id() . '--' . $conf->id()] = $provider->label() . ': ' . $conf->label();
        $form['#provider_configs'][$provider->id() . '--' . $conf->id()] = [
          'provider' => $provider->id(),
          'config' => $conf->id(),
        ];
      }
    }
    if (empty($options)) {
      return ['#markup'=> 'No auth configs found. Please add an auth config before continuing.'];
    }
    $form['provider_config'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose a default provider config'),
      '#options' => $options,
      '#empty_option' => 'N/A',
      '#default_value' => $config->get('provider') ? $config->get('provider') . '--' . $config->get('config') : NULL,
    ];
    $form['#theme'] = 'system_config_form';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $kv = $form_state->getValue('provider_config');
    $provider_config = $form['#provider_configs'][$kv];
    $this->config('salesforce_auth.settings')
      ->set('provider', $provider_config['provider'])
      ->set('config', $provider_config['config'])
      ->save();

    $this->messenger()->addStatus($this->t('Authorization settings have been saved.'));
    \Drupal::service('event_dispatcher')->dispatch(SalesforceEvents::NOTICE, new SalesforceNoticeEvent(NULL, "Authorization provider changed to %provider.", ['%provider' => $form_state->getValue('provider_config')]));
  }

}