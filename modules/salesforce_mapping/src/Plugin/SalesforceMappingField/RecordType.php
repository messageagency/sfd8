<?php

namespace Drupal\salesforce_mapping\Plugin\SalesforceMappingField;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginBase;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;

/**
 * Adapter for entity properties and fields.
 *
 * @Plugin(
 *   id = "record_type",
 *   label = @Translation("Record Type")
 * )
 */
class RecordType extends SalesforceMappingFieldPluginBase {

  /**
   * Implementation of PluginFormInterface::buildConfigurationForm.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $pluginForm = parent::buildConfigurationForm($form, $form_state);

    $options = $this->getRecordTypeOptions($form['#entity']);

    $pluginForm['salesforce_field']['#options'] = ['RecordTypeId' => 'Record Type'];
    $pluginForm['salesforce_field']['#default_value'] = 'RecordTypeId';

    $pluginForm['drupal_field_value'] += [
      '#type' => 'select',
      '#options' => $options,
      '#empty_option' => $this->t('- Select -'),
      '#default_value' => $this->config('drupal_field_value'),
      '#description' => $this->t('Select the Record Type to be pushed to Salesforce for this mapping.'),
    ];

    $pluginForm['direction']['#options'] = [
      'drupal_sf' => $pluginForm['direction']['#options']['drupal_sf'],
    ];
    $pluginForm['direction']['#default_value'] = 'drupal_sf';

    return $pluginForm;
  }

  /**
   * {@inheritdoc}
   */
  public function value(EntityInterface $entity, SalesforceMappingInterface $mapping) {
    return (string) ($this
      ->salesforceClient
      ->getRecordTypeIdByDeveloperName(
          $mapping->getSalesforceObjectType(),
          $this->config('drupal_field_value')
      ));
  }

  /**
   * {@inheritdoc}
   */
  private function getRecordTypeOptions($mapping) {
    $options = [];
    $record_types = $this->salesforceClient->getRecordTypes($mapping->getSalesforceObjectType());
    foreach ($record_types as $record_type) {
      $options[$record_type->field('DeveloperName')] = $record_type->field('Name');
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public static function isAllowed(SalesforceMappingInterface $mapping) {
    try {
      $record_types =
        self::client()->getRecordTypes($mapping->getSalesforceObjectType());

      if ($record_types === FALSE) {
        return FALSE;
      }

      return count($record_types) > 1;
    }
    catch (\Exception $e) {
      return FALSE;
    }
  }

  /**
   * Wrapper for Salesforce client service.
   */
  public static function client() {
    return \Drupal::service('salesforce.client');
  }

  /**
   * {@inheritdoc}
   *
   * @TODO figure out what it means to pull Record Type
   */
  public function pull() {
    return FALSE;
  }

}
