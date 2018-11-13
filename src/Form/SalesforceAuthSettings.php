<?php

namespace Drupal\salesforce\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Event\SalesforceNoticeEvent;
use Drupal\salesforce\SalesforceAuthManager;
use Drupal\salesforce\SalesforceAuthProviderPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SalesforceAuthSettings extends ConfigFormBase {

  protected $salesforceAuth;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory, SalesforceAuthProviderPluginManager $salesforceAuth) {
    parent::__construct($config_factory);
    $this->salesforceAuth = $salesforceAuth;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.salesforce.auth_providers')
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
    return ['salesforce.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!$this->salesforceAuth->hasProviders()) {
      return ['#markup'=> 'No auth providers have been enabled. Please enable an auth provider and create an auth config before continuing.'];
    }
    $config = $this->config('salesforce.settings');
    $form = parent::buildForm($form, $form_state);
    $options = [];
    /** @var \Drupal\salesforce\Entity\SalesforceAuthConfig $provider **/
    foreach(\Drupal::service('plugin.manager.salesforce.auth_providers')->getProviders() as $provider) {
      $options[$provider->id()] = $provider->label() . ' (' . $provider->getPlugin()->label() . ')';
    }
    if (empty($options)) {
      return ['#markup'=> 'No auth providers found. Please add an auth provider before continuing.'];
    }
    $options = ['' => '- None -'] + $options;
    $form['provider'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose a default auth provider'),
      '#options' => $options,
      '#default_value' => $config->get('salesforce_auth.provider') ? $config->get('salesforce_auth.provider') : '',
    ];
    $form['#theme'] = 'system_config_form';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('salesforce.settings')
      ->set('salesforce_auth.provider', $form_state->getValue('provider') ? $form_state->getValue('provider') : NULL)
      ->save();

    $this->messenger()->addStatus($this->t('Authorization settings have been saved.'));
    \Drupal::service('event_dispatcher')->dispatch(SalesforceEvents::NOTICE, new SalesforceNoticeEvent(NULL, "Authorization provider changed to %provider.", ['%provider' => $form_state->getValue('provider')]));
  }

}