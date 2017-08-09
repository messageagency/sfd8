<?php

namespace Drupal\salesforce_mapping\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\salesforce\Rest\RestClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\salesforce\Event\SalesforceErrorEvent;
use Drupal\salesforce\Event\SalesforceEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Salesforce Mapping Form base.
 */
class MappedObjectForm extends ContentEntityForm {

  /**
   * The storage controller.
   *
   * @var \Drupal\Core\Entity\EntityStorageControllerInterface
   */
  protected $storageController;

  /**
   * [$mappingFieldPluginManager description]
   *
   * @var [type]
   */
  protected $mappingFieldPluginManager;

  /**
   * [$pushPluginManager description]
   *
   * @var [type]
   */
  protected $pushPluginManager;

  /**
   * Mapping entity storage service.
   *
   * @var SalesforcesMappingStorage
   */
  protected $mapping_storage;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Entity manager service.
   *
   * @var EntityManagerInterface
   */
  protected $entityManager;

  /**
   * REST Client service
   *
   * @var RestClient
   */
  protected $rest;

  /**
   * Route matching service
   *
   * @var RouteMatchInterface
   */
  protected $route_match;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param RestClientInterface $rest
   *   The Rest Client.
   * @param EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   * @param RouteMatchInterface $route_match
   *   Route matching service.
   */
  public function __construct(EntityManagerInterface $entity_manager, RestClientInterface $rest, EventDispatcherInterface $event_dispatcher, RouteMatchInterface $route_match) {
    $this->entityManager = $entity_manager;
    $this->mapping_storage = $entity_manager->getStorage('salesforce_mapping');
    $this->rest = $rest;
    $this->eventDispatcher = $event_dispatcher;
    $this->route_match = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('salesforce.client'),
      $container->get('event_dispatcher'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Include the parent entity on the form.
    $form = parent::buildForm($form, $form_state);
    $url_params = $this->route_match->getParameters();
    $drupal_entity = $this->getDrupalEntityFromUrl();
    // Allow exception to bubble up here, because we shouldn't have got here if
    // there isn't a mapping.
    $mappings = $this
      ->mapping_storage
      ->loadByDrupal($drupal_entity->getEntityTypeId());
    $options = array_keys($mappings) + ['_none'];
    // Filter options based on drupal entity type.
    $form['salesforce_mapping']['widget']['#options'] = array_intersect_key($form['salesforce_mapping']['widget']['#options'], array_flip($options));

    $form['salesforce_mapping']['widget']['#reqiured'] = TRUE;
    $form['actions']['push'] = [
      '#type' => 'submit',
      '#value' => t('Push'),
      '#weight' => 10,
      '#submit' => [[$this, 'submitPush']],
    ];
    $form['actions']['pull'] = [
      '#type' => 'submit',
      '#value' => t('Pull'),
      '#weight' => 15,
      '#submit' => [[$this, 'submitPull']],
    ];
    return $form;
  }

  /**
   * Submit handler for "push" button.
   */
  public function submitPush(array &$form, FormStateInterface $form_state) {
    $drupal_entity = $this->getDrupalEntityFromUrl();

    $mapped_object = $this->entity;
    $mapped_object
      ->set('salesforce_id', $form_state->getValue('salesforce_id'))
      ->set('entity_id', $drupal_entity->id())
      ->set('entity_type_id', $drupal_entity->getEntityTypeId())
      ->set('salesforce_mapping', $form_state->getValue('salesforce_mapping'));

    // Validate mapped object. Upon failure, rebuild form.
    // Do not pass go, do not collect $200.
    $errors = $mapped_object->validate();

    if ($errors->count() > 0) {
      foreach ($errors as $error) {
        drupal_set_message($error->getMessage(), 'error');
      }
      $form_state->setRebuild();
      return;
    }

    // Push to SF.
    try {
      // push() does a save(), so no followup needed here.
      $mapped_object->push();
    }
    catch (\Exception $e) {
      $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e));
      drupal_set_message(t('Push failed with an exception: %exception', array('%exception' => $e->getMessage())), 'error');
      return;
    }

    // @TODO: more verbose feedback for successful push.
    drupal_set_message('Push successful.');
  }

  /**
   * Submit handler for "pull" button.
   */
  public function submitPull(array &$form, FormStateInterface $form_state) {
    $drupal_entity = $this->getDrupalEntityFromUrl();
    $mapped_object = $this->entity;

    $errors = $mapped_object
      ->set('salesforce_id', $form_state->getValue('salesforce_id'))
      ->set('entity_id', $drupal_entity->id())
      ->set('entity_type_id', $drupal_entity->getEntityTypeId())
      ->set('salesforce_mapping', $form_state->getValue('salesforce_mapping'))
      ->validate();

    if ($errors->count() > 0) {
      foreach ($errors as $error) {
        drupal_set_message($error->getMessage(), 'error');
      }
      $form_state->setRebuild();
      return;
    }

    // Pull from SF.
    $mapped_object->pull();

    // @TODO: more verbose feedback for successful pull.
    drupal_set_message('Pull successful.');
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->getEntity()->save();
    drupal_set_message($this->t('The mapping has been successfully saved.'));
  }

  /**
   * Retreive Salesforce's information about an object type.
   *
   * @TODO this should move to the Salesforce service
   *
   * @param string $salesforce_object_type
   *   The object type of whose records you want to retreive.
   *
   * @return array
   *   Information about the Salesforce object as provided by Salesforce.
   */
  protected function get_salesforce_object($salesforce_object_type) {
    if (empty($salesforce_object_type)) {
      return [];
    }
    // No need to cache here: Salesforce::objectDescribe implements caching.
    $sfobject = $this->rest->objectDescribe($salesforce_object_type);
    return $sfobject;
  }

  /**
   * @TODO: There must be a better way to do this.
   */
  private function getDrupalEntityFromUrl() {
    // Fetch the current entity from context.
    // @TODO what if there's more than one entity in route params?
    $params = $this->route_match->getParameters();

    if (empty($params)) {
      throw new \Exception('Invalid route parameters when attempting push to Salesforce.');
    }

    // Still a ridiculous process to extract parameters from URL.
    $keys = $params->keys();
    $key = reset($keys);
    $drupal_entity = $params->get($key);

    if (!is_object($drupal_entity)) {
      var_dump($drupal_entity);
      throw new \Exception('Invalid parameter when attempting push to Salesforce');
    }
    return $drupal_entity;
  }

}
