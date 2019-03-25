<?php

namespace Drupal\salesforce_mapping_ui\Form;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Url;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginManager;
use Drupal\salesforce_mapping\SalesforceMappableEntityTypesInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Rest\RestClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Salesforce Mapping Form base.
 */
abstract class SalesforceMappingFormBase extends EntityForm {

  /**
   * Field plugin manager.
   *
   * @var \Drupal\salesforce_mapping\SalesforceMappingFieldPluginManager
   */
  protected $mappingFieldPluginManager;

  /**
   * Salesforce client.
   *
   * @var \Drupal\salesforce\Rest\RestClientInterface
   */
  protected $client;

  /**
   * Mappable types service.
   *
   * @var \Drupal\salesforce_mapping\SalesforceMappableEntityTypesInterface
   */
  protected $mappableEntityTypes;

  /**
   * The mapping entity for this form.
   *
   * @var \Drupal\salesforce_mapping\Entity\SalesforceMappingInterface
   */
  protected $entity;

  /**
   * Bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * SalesforceMappingFormBase constructor.
   *
   * @param \Drupal\salesforce_mapping\SalesforceMappingFieldPluginManager $mappingFieldPluginManager
   *   Mapping plugin manager.
   * @param \Drupal\salesforce\Rest\RestClientInterface $client
   *   Rest client.
   * @param \Drupal\salesforce_mapping\SalesforceMappableEntityTypesInterface $mappableEntityTypes
   *   Mappable types.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   Bundle info service.
   */
  public function __construct(SalesforceMappingFieldPluginManager $mappingFieldPluginManager, RestClientInterface $client, SalesforceMappableEntityTypesInterface $mappableEntityTypes, EntityTypeBundleInfoInterface $bundleInfo) {
    $this->mappingFieldPluginManager = $mappingFieldPluginManager;
    $this->client = $client;
    $this->mappableEntityTypes = $mappableEntityTypes;
    $this->bundleInfo = $bundleInfo;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.salesforce_mapping_field'),
      $container->get('salesforce.client'),
      $container->get('salesforce_mapping.mappable_entity_types'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * Test the Salesforce connection by issuing the given api call.
   *
   * @param string $method
   *   Which method to test on the Salesforce client. Defaults to "objects".
   * @param mixed $arg
   *   An argument to send to the test method. Defaults to empty array.
   *
   * @return bool
   *   TRUE if Salesforce endpoint (or cache) responded correctly.
   */
  protected function ensureConnection($method = 'objects', $arg = [[], TRUE]) {
    $message = '';
    if ($this->client->isInit()) {
      try {
        call_user_func_array([$this->client, $method], $arg);
        return TRUE;
      }
      catch (\Exception $e) {
        // Fall through.
        $message = $e->getMessage() ?: get_class($e);
      }
    }

    $href = new Url('salesforce.auth_config');
    $this->messenger()
      ->addError($this->t('Error when connecting to Salesforce. Please <a href="@href">check your credentials</a> and try again: %message', [
        '@href' => $href->toString(),
        '%message' => $message,
      ]), 'error');
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if (!$this->entity->save()) {
      $this->messenger()->addError($this->t('An error occurred while trying to save the mapping.'));
      return;
    }

    $this->messenger()->addStatus($this->t('The mapping has been successfully saved.'));
  }

  /**
   * Retreive Salesforce's information about an object type.
   *
   * @param string $salesforce_object_type
   *   The object type of whose records you want to retreive.
   *
   * @TODO this should move to the Salesforce service
   *
   * @return \Drupal\salesforce\Rest\RestResponseDescribe
   *   Information about the Salesforce object as provided by Salesforce.
   *
   * @throws \Exception if $salesforce_object_type is not provided and
   *   $this->entity->salesforce_object_type is not set.
   */
  protected function getSalesforceObject($salesforce_object_type = '') {
    if (empty($salesforce_object_type)) {
      $salesforce_object_type = $this->entity->get('salesforce_object_type');
    }
    if (empty($salesforce_object_type)) {
      throw new \Exception('Salesforce object type not set.');
    }
    // No need to cache here: Salesforce::objectDescribe implements caching.
    return $this->client->objectDescribe($salesforce_object_type);
  }

  /**
   * Helper to retreive a list of object type options.
   *
   * @return array
   *   An array of values keyed by machine name of the object with the label as
   *   the value, formatted to be appropriate as a value for #options.
   */
  protected function getSalesforceObjectTypeOptions() {
    $sfobject_options = [];

    // Note that we're filtering SF object types to a reasonable subset.
    $config = $this->config('salesforce.settings');
    $filter = $config->get('show_all_objects') ? [] : [
      'updateable' => TRUE,
      'triggerable' => TRUE,
    ];
    $sfobjects = $this->client->objects($filter);
    foreach ($sfobjects as $object) {
      $sfobject_options[$object['name']] = $object['label'] . ' (' . $object['name'] . ')';
    }
    asort($sfobject_options);
    return $sfobject_options;
  }

}
