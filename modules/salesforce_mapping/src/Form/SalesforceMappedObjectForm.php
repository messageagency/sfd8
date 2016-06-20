<?php

/**
 * @file
 * Contains Drupal\salesforce_mapping\Form\SalesforceMappedObjectForm
 */

namespace Drupal\salesforce_mapping\Form;

// use Drupal\Core\Ajax\CommandInterface;
// use Drupal\Core\Ajax\AjaxResponse;
// use Drupal\Core\Ajax\ReplaceCommand;
// use Drupal\Core\Ajax\InsertCommand;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityStorageControllerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface;
use Drupal\salesforce\SalesforceClient;

/**
 * Salesforce Mapping Form base.
 */
class SalesforceMappedObjectForm extends ContentEntityForm {

  /**
   * The storage controller.
   *
   * @var \Drupal\Core\Entity\EntityStorageControllerInterface
   */
  protected $storageController;

  protected $SalesforceMappingFieldManager;

  protected $pushPluginManager;
  
  /**
   * @var \Drupal\salesforce\SalesforceClient
   */
  protected $salesforceClient;

  /**
   * {@inheritdoc}
   */
  // Nothing yet
  // public function buildForm(array $form, FormStateInterface $form_state) {
  //   /* @var $entity \Drupal\content_entity_example\Entity\Contact */
  //   $form = parent::buildForm($form, $form_state);
  //   $entity = $this->entity;
  //   return $form;
  // }

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
   * @todo this should move to the Salesforce service
   *
   * @param string $salesforce_object_type
   *   The object type of whose records you want to retreive.
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
