<?php

namespace Drupal\salesforce\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce_encrypt\Rest\EncryptedRestClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Event\SalesforceErrorEvent;

/**
 * Creates authorization form for Salesforce.
 */
class AuthorizeForm extends ConfigFormBase {

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
   * @param \Drupal\salesforce\Rest\RestClientInterface $salesforce_client
   *   The factory for configuration objects.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state keyvalue collection to use.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(ConfigFactoryInterface $config_factory, RestClientInterface $salesforce_client, StateInterface $state, EventDispatcherInterface $event_dispatcher) {
    parent::__construct($config_factory);
    $this->client = $salesforce_client;
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
    return 'salesforce_oauth';
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
    $config = $this->config('salesforce.settings');
    $encrypted = is_subclass_of($this->client, EncryptedRestClientInterface::class);
    $url = new Url('salesforce.oauth_callback', [], ['absolute' => TRUE]);
    drupal_set_message($this->t('Callback URL: :url', [
      ':url' => str_replace('http:', 'https:', $url->toString()),
    ]));

    $form['creds'] = [
      '#title' => $this->t('API / OAuth Connection Settings'),
      '#type' => 'details',
      '#open' => TRUE,
      '#description' => $this->t('Authorize this website to communicate with Salesforce by entering the consumer key and secret from a remote application. Submitting the form will redirect you to Salesforce where you will be asked to grant access.'),
    ];
    $form['creds']['consumer_key'] = [
      '#title' => $this->t('Salesforce consumer key'),
      '#type' => 'textfield',
      '#description' => $this->t('Consumer key of the Salesforce remote application you want to grant access to'),
      '#required' => TRUE,
      '#default_value' => $encrypted ? $this->client->decrypt($config->get('consumer_key')) : $config->get('consumer_key'),
    ];
    $form['creds']['consumer_secret'] = [
      '#title' => $this->t('Salesforce consumer secret'),
      '#type' => 'textfield',
      '#description' => $this->t('Consumer secret of the Salesforce remote application you want to grant access to'),
      '#required' => TRUE,
      '#default_value' => $encrypted ? $this->client->decrypt($config->get('consumer_secret')) : $config->get('consumer_secret'),
    ];
    $form['creds']['login_url'] = [
      '#title' => $this->t('Login URL'),
      '#type' => 'textfield',
      '#default_value' => empty($config->get('login_url')) ? 'https://login.salesforce.com' : $config->get('login_url'),
      '#description' => $this->t('Enter a login URL, either https://login.salesforce.com or https://test.salesforce.com.'),
      '#required' => TRUE,
    ];

    // If fully configured, attempt to connect to Salesforce and return a list
    // of resources.
    if ($this->client->isAuthorized()) {
      $form['creds']['#open'] = FALSE;
      $form['creds']['#description'] = $this->t('Your Salesforce salesforce instance is currently authorized. Enter credentials here only to change credentials.');
      try {
        $resources = $this->client->listResources();
        foreach ($resources->resources as $key => $path) {
          $items[] = $key . ': ' . $path;
        }
        if (!empty($items)) {
          $form['resources'] = [
            '#title' => $this->t('Your Salesforce instance is authorized and has access to the following resources:'),
            '#items' => $items,
            '#theme' => 'item_list',
          ];
        }
      }
      catch (\Exception $e) {
        // Do not allow any exceptions to interfere with displaying this page.
        drupal_set_message($e->getMessage(), 'warning');
        $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e));
      }
    }
    elseif (!$form_state->getUserInput()) {
      // Don't set this message if the form was submitted.
      drupal_set_message(t('Salesforce needs to be authorized to connect to this website.'), 'error');
    }
    $form = parent::buildForm($form, $form_state);
    $form['creds']['actions'] = $form['actions'];
    unset($form['actions']);
    return $form;
  }

  /**
   * Return whether or not the given URL is a valid endpoint.
   *
   * @return bool
   *   True is the given url is valid.
   */
  public static function validEndpoint($url) {
    return UrlHelper::isValid($url, TRUE);
  }

  /**
   * Validate handler for auth form. Basic sanity checking.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!self::validEndpoint($form_state->getValue('login_url'))) {
      $form_state->setErrorByName('login_url', t('Please enter a valid Salesforce login URL.'));
    }

    if (!$form_state->getValue('consumer_secret')) {
      $form_state->setErrorByName('consumer_secret', t('Please enter a valid consumer secret.'));
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->client->setConsumerKey($values['consumer_key']);
    $this->client->setConsumerSecret($values['consumer_secret']);
    $this->client->setLoginUrl($values['login_url']);

    try {
      $path = $this->client->getAuthEndpointUrl();
      $query = [
        'redirect_uri' => $this->client->getAuthCallbackUrl(),
        'response_type' => 'code',
        'client_id' => $this->client->getConsumerKey(),
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
  }

}
