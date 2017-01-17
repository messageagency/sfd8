<?php

namespace Drupal\salesforce_mapping\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Exception;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce_mapping\SalesforceMappingStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

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

  protected $FieldManager;

  protected $pushPluginManager;

  protected $mapping_storage;
  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager, SalesforceMappingStorage $mapping_storage, RestClient $rest) {
    $this->entityManager = $entity_manager;
    $this->mapping_storage = $mapping_storage;
    $this->rest = $rest;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager'),
      $container->get('salesforce.salesforce_mapping_storage'),
      $container->get('salesforce.client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Include the parent entity on the form.
    $form = parent::buildForm($form, $form_state);
    $url_params = \Drupal::routeMatch()->getParameters();
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
      watchdog_exception(__CLASS__, $e);
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
    $mapped_object->setNewRevision(TRUE);
    $mapped_object->save();

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
    $params = \Drupal::routeMatch()->getParameters();

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
