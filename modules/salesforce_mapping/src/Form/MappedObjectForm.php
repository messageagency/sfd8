<?php

namespace Drupal\salesforce_mapping\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\Event\SalesforceErrorEvent;
use Drupal\salesforce\Event\SalesforceEvents;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * @var RequestStack
   */
  protected $request_stack;

  /**
   * Entity type manager
   *
   * @var EntityTypeManagerInterface
   */
  protected $entity_type_manager;
  
  /**
   * Constructs a ContentEntityForm object.
   *
   * @param EntityManagerInterface $entity_manager
   *   The entity manager.
   * @param RestClientInterface $rest
   *   The Rest Client.
   * @param EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   * @param RequestStack $request_stack
   *   Route matching service.
   * @param EntityTypeManagerInterface $entity_type_manager
   *   Entity Type Manager service.
   */
  public function __construct(EntityManagerInterface $entity_manager, RestClientInterface $rest, EventDispatcherInterface $event_dispatcher, RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager) {
    $this->entityManager = $entity_manager;
    $this->mapping_storage = $entity_manager->getStorage('salesforce_mapping');
    $this->rest = $rest;
    $this->eventDispatcher = $event_dispatcher;
    $this->request = $request_stack->getCurrentRequest();
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('salesforce.client'),
      $container->get('event_dispatcher'),
      $container->get('request_stack'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Include the parent entity on the form.
    $form = parent::buildForm($form, $form_state);
    $drupal_entity = $entity_id = $entity_type_id = FALSE;
    if ($this->entity->isNew()) {
      $drupal_entity = $this->getDrupalEntityFromUrl();
    }
    else {
      $drupal_entity = $this->entity->getMappedEntity();
    }

    // Allow exception to bubble up here, because we shouldn't have got here if
    // there isn't a mapping.
    $mappings = [];
    if ($drupal_entity) {
      $form['entity_id']['widget'][0]['value']['#value'] = $drupal_entity->id();
      $form['entity_id']['widget'][0]['value']['#disabled'] = TRUE;

      $form['entity_type_id']['widget']['#options'] = [
        $drupal_entity->getEntityType()->id() =>
          $drupal_entity->getEntityType()->getLabel()
      ];
      $form['entity_type_id']['widget']['#default_value'] = 
        $drupal_entity->getEntityType()->id();
      $form['entity_type_id']['widget']['#disabled'] = TRUE;

      // Mapping cannot be changed after mapped object has been created.
      // If this is a new mapped object, and a drupal entity is given, mappings
      // depend on given entity type.
      if ($this->entity->isNew()) {
        $mappings = $this
          ->mapping_storage
          ->loadByDrupal($drupal_entity->getEntityTypeId());
      }
      else {
        $form['salesforce_mapping']['widget']['#disabled'] = TRUE;
      }
    }
    else {
      // If entity is not set, entity types are dependent on available mappings.
      $mappings = $this
        ->mapping_storage
        ->loadMultiple();
      foreach ($mappings as $mapping) {
        $entity_type_id = $mapping->getDrupalEntityType();
        $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
        $form['entity_type_id']['widget']['#options'][$entity_type_id] = $entity_type->getLabel();
      }
    }

    if ($mappings) {
      $options = array_keys($mappings);
      // Filter options based on drupal entity type.
      $form['salesforce_mapping']['widget']['#options'] = array_intersect_key($form['salesforce_mapping']['widget']['#options'], array_flip($options));
    }

    $form['actions']['push'] = [
      '#type' => 'submit',
      '#value' => t('Push'),
      '#weight' => 5,
      '#submit' => [[$this, 'submitPush']],
    ];

    $form['actions']['pull'] = [
      '#type' => 'submit',
      '#value' => t('Pull'),
      '#weight' => 6,
      '#submit' => [[$this, 'submitPull']],
    ];

    return $form;
  }

  /**
   * Submit handler for "push" button.
   */
  public function submitPush(array &$form, FormStateInterface $form_state) {
    $drupal_entity = $form['drupal_entity']['#value'];

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
    $drupal_entity = $form['drupal_entity']['#value'];
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
    $entity_type_id = $this->request->query->get('entity_type_id');
    $entity_id = $this->request->query->get('entity_id');
    if (empty($entity_id) || empty($entity_type_id)) {
      return FALSE;
    }
    return $this->entityTypeManager
      ->getStorage($entity_type_id)
      ->load($entity_id);
  }

}
