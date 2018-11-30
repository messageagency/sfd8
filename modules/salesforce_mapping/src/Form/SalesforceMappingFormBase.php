<?php

namespace Drupal\salesforce_mapping\Form;

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
   * SalesforceMappingFormBase constructor.
   *
   * @param \Drupal\salesforce_mapping\SalesforceMappingFieldPluginManager $mappingFieldPluginManager
   *   Mapping plugin manager.
   * @param \Drupal\salesforce\Rest\RestClientInterface $client
   *   Rest client.
   * @param \Drupal\salesforce_mapping\SalesforceMappableEntityTypesInterface $mappableEntityTypes
   *   Mappable types.
   */
  public function __construct(SalesforceMappingFieldPluginManager $mappingFieldPluginManager, RestClientInterface $client, SalesforceMappableEntityTypesInterface $mappableEntityTypes) {
    $this->mappingFieldPluginManager = $mappingFieldPluginManager;
    $this->client = $client;
    $this->mappableEntityTypes = $mappableEntityTypes;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.salesforce_mapping_field'),
      $container->get('salesforce.client'),
      $container->get('salesforce_mapping.mappable_entity_types')
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
   * @param string $salesforce_object_type
   *   The object type of whose records you want to retreive.
   *
   * @TODO this should move to the Salesforce service
   *
   * @return \Drupal\salesforce\Rest\RestResponseDescribe
   *   Information about the Salesforce object as provided by Salesforce.
   *
   * @throws Exception if $salesforce_object_type is not provided and
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

}
