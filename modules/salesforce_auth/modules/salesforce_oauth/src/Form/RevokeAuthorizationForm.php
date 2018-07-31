<?php

namespace Drupal\salesforce_oauth\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Event\SalesforceNoticeEvent;
use Drupal\salesforce\Rest\RestClientInterface;

use Drupal\salesforce_oauth\SalesforceAuthProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class RevokeAuthorizationForm extends EntityForm {

  /**
   * The Salesforce REST client.
   *
   * @var \Drupal\salesforce\Rest\RestClientInterface
   */
  protected $oauth;

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
  protected $messenger;

  /**
   * The config entity.
   *
   * @var \Drupal\salesforce_oauth\Entity\OAuthConfig
   */
  protected $entity;

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
  public function __construct(EventDispatcherInterface $event_dispatcher, SalesforceAuthProvider $oauth, MessengerInterface $messenger) {
    $this->eventDispatcher = $event_dispatcher;
    $this->oauth = $oauth;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('event_dispatcher'),
      $container->get('salesforce_oauth.auth_provider'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['actions']['#title'] = 'Are you sure you want to revoke authorization?';
    $form['actions']['#type'] = 'details';
    $form['actions']['#open'] = TRUE;
    $form['actions']['#description'] = t('Revoking authorization will destroy Salesforce OAuth and refresh tokens. Drupal will no longer be authorized to communicate with Salesforce using this config.');
    $form['actions']['submit']['#value'] = t('Revoke authorization');

    // By default, render the form using system-config-form.html.twig.
    $form['#theme'] = 'system_config_form';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\salesforce_oauth\Entity\OAuthConfig*/
    $this->oauth->revokeAuthorization($this->entity);
    $this->messenger->addStatus($this->t('Salesforce OAuth tokens have been revoked.'));
    $this->eventDispatcher->dispatch(SalesforceEvents::NOTICE, new SalesforceNoticeEvent(NULL, "Salesforce OAuth tokens revoked."));
  }

}