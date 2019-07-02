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
  protected $client;

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
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RestClientInterface $salesforce_client, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($config_factory);
    $this->client = $salesforce_client;
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
    $definition = \Drupal::service('config.typed')->getDefinition('salesforce.settings');
    $definition = $definition['mapping'];

    $form['use_latest'] = [
      '#title' => $this->t($definition['use_latest']['label']),
      '#type' => 'checkbox',
      '#description' => $this->t($definition['use_latest']['description']),
      '#default_value' => $config->get('use_latest'),
    ];
    $versions = [];
    try {
      $versions = $this->getVersionOptions();
    }
    catch (\Exception $e) {
      $href = new Url('salesforce.admin_config_salesforce');
      $this->messenger()->addError($this->t('Error when connecting to Salesforce. Please <a href="@href">check your credentials</a> and try again: %message', ['@href' => $href->toString(), '%message' => $e->getMessage()]));
    }

    $form['rest_api_version'] = [
      '#title' => $this->t($definition['rest_api_version']['label']),
      '#description' => $this->t($definition['rest_api_version']['description']),
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

    if (\Drupal::moduleHandler()->moduleExists('salesforce_push')) {
      $form['global_push_limit'] = [
        '#title' => $this->t($definition['global_push_limit']['label']),
        '#type' => 'number',
        '#description' => $this->t($definition['global_push_limit']['description']),
        '#required' => TRUE,
        '#default_value' => $config->get('global_push_limit'),
        '#min' => 0,
      ];
    }

    if (\Drupal::moduleHandler()->moduleExists('salesforce_pull')) {
      $form['pull_max_queue_size'] = [
        '#title' => $this->t($definition['pull_max_queue_size']['label']),
        '#type' => 'number',
        '#description' => $this->t($definition['pull_max_queue_size']['description']),
        '#required' => TRUE,
        '#default_value' => $config->get('pull_max_queue_size'),
        '#min' => 0,
      ];
    }

    if (\Drupal::moduleHandler()->moduleExists('salesforce_mapping')) {
      $form['limit_mapped_object_revisions'] = [
        '#title' => $this->t($definition['limit_mapped_object_revisions']['label']),
        '#description' => $this->t($definition['limit_mapped_object_revisions']['description']),
        '#type' => 'number',
        '#required' => TRUE,
        '#default_value' => $config->get('limit_mapped_object_revisions'),
        '#min' => 0,
      ];

      $form['show_all_objects'] = [
        '#title' => $this->t($definition['show_all_objects']['label']),
        '#description' => $this->t($definition['show_all_objects']['description']),
        '#type' => 'checkbox',
        '#default_value' => $config->get('show_all_objects'),
      ];
    }

    if (\Drupal::moduleHandler()->moduleExists('salesforce_push') || \Drupal::moduleHandler()->moduleExists('salesforce_pull')) {
      $form['standalone'] = [
        '#title' => $this->t($definition['standalone']['label']),
        '#description' => $this->t($definition['standalone']['description']),
        '#type' => 'checkbox',
        '#default_value' => $config->get('standalone'),
      ];

      if (\Drupal::moduleHandler()->moduleExists('salesforce_push')) {
        $standalone_push_url = Url::fromRoute(
          'salesforce_push.endpoint',
          ['key' => \Drupal::state()->get('system.cron_key')],
          ['absolute' => TRUE]);
        $form['standalone_push_url'] = [
          '#type' => 'item',
          '#title' => $this->t('Standalone Push URL'),
          '#markup' => $this->t('<a href="@url">@url</a>', ['@url' => $standalone_push_url->toString()]),
          '#states' => [
            'visible' => [
              ':input#edit-standalone' => ['checked' => TRUE],
            ],
          ],
        ];
      }
      if (\Drupal::moduleHandler()->moduleExists('salesforce_pull')) {
        $standalone_pull_url = Url::fromRoute(
          'salesforce_pull.endpoint',
          ['key' => \Drupal::state()->get('system.cron_key')],
          ['absolute' => TRUE]);
        $form['standalone_pull_url'] = [
          '#type' => 'item',
          '#title' => $this->t('Standalone Pull URL'),
          '#markup' => $this->t('<a href="@url">@url</a>', ['@url' => $standalone_pull_url->toString()]),
          '#states' => [
            'visible' => [
              ':input#edit-standalone' => ['checked' => TRUE],
            ],
          ],
        ];
      }
    }

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
    $config->set('limit_mapped_object_revisions', $form_state->getValue('limit_mapped_object_revisions'));
    $use_latest = $form_state->getValue('use_latest');
    $config->set('use_latest', $use_latest);
    if (!$use_latest) {
      $versions = $this->client->getVersions();
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
   *   The version options.
   */
  protected function getVersionOptions() {
    $versions = $this->client->getVersions();
    array_walk($versions,
      function (&$item, $key) {
        $item = $item['label'];
      });
    return $versions;
  }

}
