<?php

/**
 * @file
 * Contains Drupal\salesforce_mapping\Form\MappedObjectForm
 */

namespace Drupal\salesforce_mapping\Form;

// use Drupal\Core\Ajax\CommandInterface;
// use Drupal\Core\Ajax\AjaxResponse;
// use Drupal\Core\Ajax\ReplaceCommand;
// use Drupal\Core\Ajax\InsertCommand;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_mapping\Entity\MappedObject;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface;

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

  protected $SalesforceMappingFieldManager;

  protected $pushPluginManager;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   The entity manager.
   */
  public function __construct(EntityManagerInterface $entity_manager) {
    $this->entityManager = $entity_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Include the parent entity on the form.
    $form = parent::buildForm($form, $form_state);
    $url_params = \Drupal::routeMatch()->getParameters();
    $form['salesforce_mapping']['widget']['#reqiured'] = TRUE;
    $form['actions']['push'] = array(
      '#type' => 'submit',
      '#value' => t('Push'),
      '#weight' => 10,
      '#submit' => [[$this, 'submitPush']],
    );
    return $form;
  }

  public function submitPush(array &$form, FormStateInterface $form_state) {
    // Fetch the current entity from context.
    // @TODO what if there's more than one entity in route params?
    $params = \Drupal::routeMatch()->getParameters();
    
    if (empty($params)) {
      throw new \Exception('Invalid route parameters when attempting push to Salesforce.');
    }
    $mapped_object = $this->entity;

    // Still a ridiculous process to extract parameters from URL.
    $keys = $params->keys();
    $key = reset($keys);
    $drupal_entity = $params->get($key);

    if (!is_object($drupal_entity)) {
      var_dump($drupal_entity);
      throw new \Exception('Invalid parameter when attempting push to Salesforce');
    }

    // Fetch the sfid from form input, if given
    $sfid =  $form_state->getValue('salesforce_id');

    // Create a mapped object
    $mapped_object = new MappedObject(    array(
      'salesforce_id' => $sfid,
      'entity_id' => $drupal_entity->id(),
      'entity_type_id' => $drupal_entity->getEntityTypeId()
    ));
    $mapped_object->set('salesforce_mapping', $form_state->getValue('salesforce_mapping'));

    // Validate mapped object. Upon failure, rebuild form. Do not pass go, do not collect $200.

    $errors = $mapped_object->validate();

    if ($errors->count() > 0) {
      foreach ($errors as $error) {
        drupal_set_message($error->getMessage(), 'error');
      }
      $form_state->setRebuild();
      return;
    }

    // Push to SF.
    $result = $mapped_object->push();

    $mapped_object->set('salesforce_id', $result['id']);

    // Save mapped object.
    $mapped_object->save();
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->getEntity()->save();
    // $this->entity->save();
    drupal_set_message($this->t('The mapping has been successfully saved.'));
  }

  /**
   * Retreive Salesforce's information about an object type.
   * @TODO this should move to the Salesforce service
   *
   * @param string $salesforce_object_type
   *   The object type of whose records you want to retreive.
   *
   * 
   * @return array
   *   Information about the Salesforce object as provided by Salesforce.
   */
  protected function get_salesforce_object($salesforce_object_type) {
    if (empty($salesforce_object_type)) {
      return array();
    }
    // No need to cache here: Salesforce::objectDescribe implements caching.
    $sfapi = salesforce_get_api();
    $sfobject = $sfapi->objectDescribe($salesforce_object_type);
    return $sfobject;
  }

}
