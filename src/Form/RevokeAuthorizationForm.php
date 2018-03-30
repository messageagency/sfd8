<?php

namespace Drupal\salesforce\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Event\SalesforceNoticeEvent;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class RevokeAuthorizationForm extends ConfigFormBase {

  /**
   * The Salesforce REST client.
   *
   * @var \Drupal\salesforce\Rest\RestClientInterface
   */
  protected $sf_client;

  /**
   * The sevent dispatcher service..
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
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
    $form = parent::buildForm($form, $form_state);
    $form['actions']['#title'] = 'Are you sure you want to revoke authorization?';
    $form['actions']['#type'] = 'details';
    $form['actions']['#open'] = TRUE;
    $form['actions']['#description'] = t('Revoking authorization will destroy Salesforce OAuth and refresh tokens. Drupal will no longer be authorized to communicate with Salesforce.');
    $form['actions']['submit']['#value'] = t('Revoke authorization');

    // By default, render the form using system-config-form.html.twig.
    $form['#theme'] = 'system_config_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->sf_client->setAccessToken('');
    $this->sf_client->setRefreshToken('');
    $this->sf_client->setInstanceUrl('');
    $this->sf_client->setIdentity(FALSE);
    drupal_set_message($this->t('Salesforce OAuth tokens have been revoked.'));
    $this->eventDispatcher->dispatch(SalesforceEvents::NOTICE, new SalesforceNoticeEvent(NULL, "Salesforce OAuth tokens revoked."));
  }

}