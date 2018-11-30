<?php

namespace Drupal\salesforce_mapping\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Event\SalesforceErrorEvent;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\SFID;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Salesforce Mapping Form base.
 */
class MappedObjectForm extends ContentEntityForm {

  /**
   * Mapping entity storage service.
   *
   * @var \Drupal\salesforce_mapping\SalesforceMappingStorage
   */
  protected $mappingStorage;

  /**
   * Mapped object storage service.
   *
   * @var \Drupal\salesforce_mapping\MappedObjectStorage
   */
  protected $mappedObjectStorage;

  /**
   * Event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Route matching service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $request;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * MappedObjectForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   Entity manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityTypeBundleInfo
   *   Bundle info service.
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   *   Time service.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(EntityManagerInterface $entity_manager, EntityTypeBundleInfoInterface $entityTypeBundleInfo, TimeInterface $time, EventDispatcherInterface $event_dispatcher, RequestStack $request_stack, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_manager, $entityTypeBundleInfo, $time);
    $this->eventDispatcher = $event_dispatcher;
    $this->request = $request_stack->getCurrentRequest();
    $this->entityTypeManager = $entity_type_manager;
    $this->mappingStorage = $entity_type_manager->getStorage('salesforce_mapping');
    $this->mappedObjectStorage = $entity_type_manager->getStorage('salesforce_mapped_object');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
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
      if ($drupal_entity = $this->getDrupalEntityFromUrl()) {
        $form['drupal_entity']['widget'][0]['target_type']['#default_value'] = $drupal_entity->getEntityTypeId();
        $form['drupal_entity']['widget'][0]['target_id']['#default_value'] = $drupal_entity;
      }
    }

    // Allow exception to bubble up here, because we shouldn't have got here if
    // there isn't a mapping.
    // If entity is not set, entity types are dependent on available mappings.
    $mappings = $this
      ->mappingStorage
      ->loadMultiple();

    // @TODO #states for entity-type + salesforce mapping dependency

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
      '#validate' => [[$this, 'validateForm'], [$this, 'validatePush']],
    ];

    $form['actions']['pull'] = [
      '#type' => 'submit',
      '#value' => t('Pull'),
      '#weight' => 6,
      '#submit' => [[$this, 'submitPull']],
      '#validate' => [[$this, 'validateForm'], [$this, 'validatePull']],
    ];

    return $form;
  }

  /**
   * Verify that entity type and mapping agree.
   */
  public function validatePush(array &$form, FormStateInterface $form_state) {
    $drupal_entity_array = $form_state->getValue(['drupal_entity', 0]);

    // Verify entity was given - required for push.
    if (empty($drupal_entity_array['target_id'])) {
      $form_state->setErrorByName('drupal_entity][0][target_id', t('Please specify an entity to push.'));
      return;
    }
  }

  /**
   * Salesforce ID is required for a pull.
   */
  public function validatePull(array &$form, FormStateInterface $form_state) {
    // Verify SFID was given - required for pull.
    $sfid = $form_state->getValue(['salesforce_id', 0, 'value'], FALSE);
    if (!$sfid) {
      $form_state->setErrorByName('salesforce_id', t('Please specify a Salesforce ID to pull.'));
      return;
    }
  }

  /**
   * Submit handler for "push" button.
   */
  public function submitPush(array &$form, FormStateInterface $form_state) {
    $drupal_entity_array = $form_state->getValue(['drupal_entity', 0]);
    $mapped_object = $this->entity;
    $mapped_object
      ->set('drupal_entity', $drupal_entity_array)
      ->set('salesforce_mapping', $form_state->getValue([
        'salesforce_mapping', 0, 'target_id',
      ]));

    if ($sfid = $form_state->getValue(['salesforce_id', 0, 'value'], FALSE)) {
      $mapped_object->set('salesforce_id', (string) new SFID($sfid));
    }
    else {
      $mapped_object->set('salesforce_id', '');
    }

    // Push to SF.
    try {
      // Push calls save(), so this is all we need to do:
      $mapped_object->push();
    }
    catch (\Exception $e) {
      $mapped_object->delete();
      $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e));
      drupal_set_message(t('Push failed with an exception: %exception', ['%exception' => $e->getMessage()]), 'error');
      $form_state->setRebuild();
      return;
    }

    // @TODO: more verbose feedback for successful push.
    drupal_set_message('Push successful.');
    $form_state->setRedirect('entity.salesforce_mapped_object.canonical', ['salesforce_mapped_object' => $mapped_object->id()]);
  }

  /**
   * Submit handler for "pull" button.
   */
  public function submitPull(array &$form, FormStateInterface $form_state) {
    $mapping_id = $form_state->getValue(['salesforce_mapping', 0, 'target_id']);
    $sfid = new SFID($form_state->getValue(['salesforce_id', 0, 'value']));
    $mapped_object = $this->entity
      ->set('salesforce_id', (string) $sfid)
      ->set('salesforce_mapping', $mapping_id);
    // Create stub entity.
    $drupal_entity_array = $form_state->getValue(['drupal_entity', 0]);
    if ($drupal_entity_array['target_id']) {
      $drupal_entity = $this->entityTypeManager
        ->getStorage($drupal_entity_array['target_type'])
        ->load($drupal_entity_array['target_id']);
      $mapped_object->set('drupal_entity', $drupal_entity);
    }
    else {
      $drupal_entity = $this->entityTypeManager
        ->getStorage($drupal_entity_array['target_type'])
        ->create(['salesforce_pull' => TRUE]);
      $mapped_object->set('drupal_entity', NULL);
      $mapped_object->setDrupalEntityStub($drupal_entity);
    }

    try {
      // Pull from SF. Save first to pass local validation.
      $mapped_object->save();
      $mapped_object->pull();
    }
    catch (\Exception $e) {
      $this->eventDispatcher->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e));
      drupal_set_message(t('Pull failed with an exception: %exception', ['%exception' => $e->getMessage()]), 'error');
      $form_state->setRebuild();
      return;
    }

    // @TODO: more verbose feedback for successful pull.
    drupal_set_message('Pull successful.');
    $form_state->setRedirect('entity.salesforce_mapped_object.canonical', ['salesforce_mapped_object' => $mapped_object->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->getEntity()->save();
    drupal_set_message($this->t('The mapping has been successfully saved.'));
    $form_state->setRedirect('entity.salesforce_mapped_object.canonical', ['salesforce_mapped_object' => $this->getEntity()->id()]);
  }

  /**
   * Helper to fetch the contextual Drupal entity.
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
