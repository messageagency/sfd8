<?php

namespace Drupal\salesforce\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\State\StateInterface;
use Drupal\salesforce\Rest\RestClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Event\SalesforceErrorEvent;

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
   * The state keyvalue collection.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\salesforce\RestClient $salesforce_client
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state keyvalue collection to use.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RestClientInterface $salesforce_client, StateInterface $state, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($config_factory);
    $this->sf_client = $salesforce_client;
    $this->state = $state;
    $this->eventDispatcher = $event_dispatcher;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('salesforce.client'),
      $container->get('state'),
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
    $versions = $this->getVersionOptions();
    $form['rest_api_version'] = [
      '#title' => $this->t('Select a specific Rest API version'),
      '#type' => 'select',
      '#options' => $versions,
      '#tree' => TRUE,
      '#default_value' => $config->get('rest_api_version')['version'],
      '#states' => [
        'visible' => [
          ':input[name="use_latest"]' => ['checked' => FALSE],
        ]
      ],
    ];

    $form['push_limit'] = [
      '#title' => $this->t('Global push limit'),
      '#type' => 'number',
      '#description' => $this->t('Set the maximum number of records to be processed during each push queue process. Enter 0 for no limit.'),
      '#required' => TRUE,
      '#min' => 0,
    ];

    $form['pull_max_queue_size'] = [
      '#title' => $this->t('Pull queue max size'),
      '#type' => 'password',
      '#description' => $this->t('Set the maximum number of items which can be enqueued for pull at any given time. Note this setting is not exactly analogous to the push queue limit, since Drupal Cron API does not offer such granularity. Enter 0 for no limit.'),
      '#required' => TRUE,
      '#min' => 0,
    ];

    $form['show_all_objects'] = [
      '#title' => $this->t('Show all objects'),
      '#description' => $this->t('Check this box to expose all Salesforce objects to the Mapping interface. By default, Salesforce objects like custom settings, read-only objects, non-triggerable objects, etc. are hidden from the Salesforce Mapping interface to improve usability.'),
      '#type' => 'checkbox',
      '#default_value' => $config->get('show_all_objects'),
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
    $values = $form_state->getValues();
    $this->sf_client->setConsumerKey($values['consumer_key']);
    $this->sf_client->setConsumerSecret($values['consumer_secret']);
    $this->sf_client->setLoginUrl($values['login_url']);

    try {
      $path = $this->sf_client->getAuthEndpointUrl();
      $query = [
        'redirect_uri' => $this->sf_client->getAuthCallbackUrl(),
        'response_type' => 'code',
        'client_id' => $values['consumer_key'],
      ];

      // Send the user along to the Salesforce OAuth login form. If successful,
      // the user will be redirected to {redirect_uri} to complete the OAuth
      // handshake.
      $form_state->setResponse(new TrustedRedirectResponse($path . '?' . http_build_query($query), 302));
    }
    catch (RequestException $e) {
      drupal_set_message(t("Error during authorization: %message", ['%message' => $e->getMessage()]), 'error');
      $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e));
    }

    $config = $this->config('salesforce.settings');
    $config->set('show_all_objects', $form_state->getValue('show_all_objects'));
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
    array_walk($versions, function(&$item, $key) { $item = $item['label'];} );
    return $versions;
  }

}
