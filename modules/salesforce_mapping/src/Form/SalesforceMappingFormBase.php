<?php

namespace Drupal\salesforce_mapping\Form;

use Drupal\salesforce_mapping\SalesforceMappingFieldPluginManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\Rest\RestClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Salesforce Mapping Form base.
 */
abstract class SalesforceMappingFormBase extends EntityForm {

  protected $mappingFieldPluginManager;
  protected $client;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Entity\EntityStorageControllerInterface
   *   Need this to fetch the appropriate field mapping
   * @param \Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface
   *   Need this to fetch the mapping field plugins
   *
   * @throws RuntimeException
   */
  public function __construct(SalesforceMappingFieldPluginManager $mappingFieldPluginManager, RestClientInterface $client) {
    $this->mappingFieldPluginManager = $mappingFieldPluginManager;
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.salesforce_mapping_field'),
      $container->get('salesforce.client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    if (!$this->entity->save()) {
      drupal_set_message($this->t('An error occurred while trying to save the mapping.'));
      return;
    }

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
   * @return RestResponse_Describe
   *   Information about the Salesforce object as provided by Salesforce.
   *
   * @throws Exception if $salesforce_object_type is not provided and
   *   $this->entity->salesforce_object_type is not set.
   */
  protected function get_salesforce_object($salesforce_object_type = '') {
    if (empty($salesforce_object_type)) {
      $salesforce_object_type = $this->entity->get('salesforce_object_type');
    }
    if (empty($salesforce_object_type)) {
      throw new \Exception('Salesforce object type not set.');
    }
    // No need to cache here: Salesforce::objectDescribe implements caching.
    return $this->client->objectDescribe($salesforce_object_type);
  }

}
