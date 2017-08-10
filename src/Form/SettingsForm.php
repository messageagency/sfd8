<?php

namespace Drupal\salesforce\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Rest\RestClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Url;

/**
 * Creates authorization form for Salesforce.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The Salesforce REST client.
   *
   * @var \Drupal\salesforce\Rest\RestClientInterface
   */
  protected $sf_client;

  /**
   * The sevent dispatcher service..
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $eventDispatcher;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\salesforce\Rest\RestClientInterface $salesforce_client
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RestClientInterface $salesforce_client, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($config_factory);
    $this->sf_client = $salesforce_client;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('salesforce.client'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'salesforce_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'salesforce.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // We're not actually doing anything with this, but may figure out
    // something that makes sense.
    $config = $this->config('salesforce.settings');

    $form['use_latest'] = [
      '#title' => $this->t('Use Latest Rest API version (recommended)'),
      '#type' => 'checkbox',
      '#description' => $this->t('Always use the latest Rest API version when connecting to Salesforce. In general, Rest API is backwards-compatible for many years. Unless you have a very specific reason, you should probably just use the latest version.'),
      '#default_value' => $config->get('use_latest'),
    ];
    $versions = [];
    try {
      $versions = $this->getVersionOptions();
    }
    catch (\Exception $e) {
      $href = new Url('salesforce.authorize');
      drupal_set_message($this->t('Error when connecting to Salesforce. Please <a href="@href">check your credentials</a> and try again: %message', ['@href' => $href->toString(), '%message' => $e->getMessage()]), 'error');
    }

    $form['rest_api_version'] = [
      '#title' => $this->t('Select a specific Rest API version'),
      '#type' => 'select',
      '#options' => $versions,
      '#tree' => TRUE,
      '#default_value' => $config->get('rest_api_version')['version'],
      '#states' => [
        'visible' => [
          ':input[name="use_latest"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['global_push_limit'] = [
      '#title' => $this->t('Global push limit'),
      '#type' => 'number',
      '#description' => $this->t('Set the maximum number of records to be processed during each push queue process. Enter 0 for no limit.'),
      '#required' => TRUE,
      '#default_value' => $config->get('global_push_limit'),
      '#min' => 0,
    ];

    $form['pull_max_queue_size'] = [
      '#title' => $this->t('Pull queue max size'),
      '#type' => 'number',
      '#description' => $this->t('Set the maximum number of items which can be enqueued for pull at any given time. Note this setting is not exactly analogous to the push queue limit, since Drupal Cron API does not offer such granularity. Enter 0 for no limit.'),
      '#required' => TRUE,
      '#default_value' => $config->get('pull_max_queue_size'),
      '#min' => 0,
    ];

    $form['show_all_objects'] = [
      '#title' => $this->t('Show all objects'),
      '#description' => $this->t('Check this box to expose all Salesforce objects to the Mapping interface. By default, Salesforce objects like custom settings, read-only objects, non-triggerable objects, etc. are hidden from the Salesforce Mapping interface to improve usability.'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('show_all_objects'),
    ];

    $form['standalone'] = [
      '#title' => $this->t('Standalone Push Processing'),
      '#description' => $this->t('Enable standalone push processing, and do not process push mappings during cron. Note: when enabled, you must set up your own service to query this endpoint.'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('standalone'),
    ];

    $standalone_url = Url::fromRoute(
        'salesforce_push.endpoint',
        ['key' => \Drupal::state()->get('system.cron_key')],
        ['absolute' => TRUE]);
    $form['standalone_url'] = [
      '#type' => 'item',
      '#title' => $this->t('Standalone URL'),
      '#markup' => $this->t('<a href="@url">@url</a>', ['@url' => $standalone_url->toString()]),
      '#states' => [
        'visible' => [
          ':input#edit-standalone' => ['checked' => TRUE],
        ],
      ],
    ];

    $form = parent::buildForm($form, $form_state);
    $form['creds']['actions'] = $form['actions'];
    unset($form['actions']);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('salesforce.settings');
    $config->set('show_all_objects', $form_state->getValue('show_all_objects'));
    $config->set('standalone', $form_state->getValue('standalone'));
    $config->set('global_push_limit', $form_state->getValue('global_push_limit'));
    $config->set('pull_max_queue_size', $form_state->getValue('pull_max_queue_size'));
    $use_latest = $form_state->getValue('use_latest');
    $config->set('use_latest', $use_latest);
    if (!$use_latest) {
      $versions = $this->sf_client->getVersions();
      $version = $versions[$form_state->getValue('rest_api_version')];
      $config->set('rest_api_version', $version);
    }
    $config->save();
    parent::submitForm($form, $form_state);
  }

  /**
   * Helper method to generate Salesforce option list for select element.
   *
   * @return array
   */
  protected function getVersionOptions() {
    $versions = $this->sf_client->getVersions();
    array_walk($versions,
      function (&$item, $key) {
        $item = $item['label'];
      });
    return $versions;
  }

}
