<?php

namespace Drupal\salesforce_mapping\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;

/**
 * Salesforce Mapping Form base.
 */
abstract class SalesforceMappingFormCrudBase extends SalesforceMappingFormBase {

  /**
   * The storage controller.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storageController;

  protected $SalesforceMappingFieldManager;

  protected $pushPluginManager;

  /**
   * {@inheritdoc}
   *
   * @TODO this function is almost 200 lines. Look into leveraging core Entity
   *   interfaces like FieldsDefinition (or something). Look at breaking this up
   *   into smaller chunks.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $mapping = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $mapping->label(),
      '#required' => TRUE,
      '#weight' => -30,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#required' => TRUE,
      '#default_value' => $mapping->id(),
      '#maxlength' => 255,
      '#machine_name' => [
        'exists' => ['Drupal\salesforce_mapping\Entity\SalesforceMapping', 'load'],
        'source' => ['label'],
      ],
      '#disabled' => !$mapping->isNew(),
      '#weight' => -20,
    ];

    $form['drupal_entity'] = [
      '#title' => $this->t('Drupal entity'),
      '#type' => 'details',
      '#attributes' => [
        'id' => 'edit-drupal-entity',
      ],
      // Gently discourage admins from breaking existing fieldmaps:
      '#open' => $mapping->isNew(),
    ];

    $entity_types = $this->get_entity_type_options();
    $form['drupal_entity']['drupal_entity_type'] = [
      '#title' => $this->t('Drupal Entity Type'),
      '#id' => 'edit-drupal-entity-type',
      '#type' => 'select',
      '#description' => $this->t('Select a Drupal entity type to map to a Salesforce object.'),
      '#options' => $entity_types,
      '#default_value' => $mapping->get('drupal_entity_type'),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['drupal_entity']['drupal_bundle'] = ['#title' => 'Drupal Bundle', '#tree' => TRUE];
    foreach ($entity_types as $entity_type => $label) {
      $bundle_info = \Drupal::entityManager()->getBundleInfo($entity_type);
      if (empty($bundle_info)) {
        continue;
      }
      $form['drupal_entity']['drupal_bundle'][$entity_type] = [
        '#title' => $this->t('@entity_type Bundle', ['@entity_type' => $label]),
        '#type' => 'select',
        '#empty_option' => $this->t('- Select -'),
        '#options' => [],
        '#states' => [
          'visible' => [
            ':input#edit-drupal-entity-type' => ['value' => $entity_type],
          ],
          'required' => [
            ':input#edit-drupal-entity-type, dummy1' => ['value' => $entity_type],
          ],
          'disabled' => [
            ':input#edit-drupal-entity-type, dummy2' => ['!value' => $entity_type],
          ],
        ],
      ];
      foreach ($bundle_info as $key => $info) {
        $form['drupal_entity']['drupal_bundle'][$entity_type]['#options'][$key] = $info['label'];
        if ($key == $mapping->get('drupal_bundle')) {
          $form['drupal_entity']['drupal_bundle'][$entity_type]['#default_value'] = $key;
        }
      }
    }

    $form['salesforce_object'] = [
      '#title' => $this->t('Salesforce object'),
      '#id' => 'edit-salesforce-object',
      '#type' => 'details',
      // Gently discourage admins from breaking existing fieldmaps:
      '#open' => $mapping->isNew(),
    ];

    $salesforce_object_type = '';
    if (!empty($form_state->getValues()) && !empty($form_state->getValue('salesforce_object_type'))) {
      $salesforce_object_type = $form_state->getValue('salesforce_object_type');
    }
    elseif ($mapping->get('salesforce_object_type')) {
      $salesforce_object_type = $mapping->get('salesforce_object_type');
    }
    $form['salesforce_object']['salesforce_object_type'] = [
      '#title' => $this->t('Salesforce Object'),
      '#id' => 'edit-salesforce-object-type',
      '#type' => 'select',
      '#description' => $this->t('Select a Salesforce object to map.'),
      '#default_value' => $salesforce_object_type,
      '#options' => $this->get_salesforce_object_type_options(),
      '#ajax' => [
        'callback' => [$this, 'salesforce_record_type_callback'],
        'wrapper' => 'edit-salesforce-object',
      ],
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];

    $form['salesforce_object']['salesforce_record_type'] = [
      '#id' => 'edit-salesforce-record-type',
    ];

    if ($salesforce_object_type) {
      // Check for custom record types.
      $salesforce_record_type = $mapping->get('salesforce_record_type');
      $salesforce_record_type_options = $this->get_salesforce_record_type_options($salesforce_object_type, $form_state);
      if (count($salesforce_record_type_options) > 1) {
        // There are multiple record types for this object type, so the user
        // must choose one of them.  Provide a select field.
        $form['salesforce_object']['salesforce_record_type'] = [
          '#title' => $this->t('Salesforce Record Type'),
          '#type' => 'select',
          '#description' => $this->t('Select a Salesforce record type to map.'),
          '#default_value' => $salesforce_record_type,
          '#options' => $salesforce_record_type_options,
          '#empty_option' => $this->t('- Select -'),
          // Do not make it required to preserve graceful degradation
        ];
      }
      else {
        // There is only one record type for this object type.  Don't bother the
        // user and just set the single record type by default.
        $form['salesforce_object']['salesforce_record_type'] = [
          '#title' => $this->t('Salesforce Record Type'),
          '#type' => 'hidden',
          '#value' => '',
        ];
      }
    }

    $form['salesforce_object']['pull_trigger_date'] = [
      '#type' => 'select',
      '#title' => t('Date field to trigger pull'),
      '#description' => t('Select a date field to base pull triggers on. (Default of "Last Modified Date" is usually appropriate).'),
      '#required' => $this->entity->get('salesforce_object_type'),
      '#default_value' => $this->entity->get('pull_trigger_date')
      ? $this->entity->get('pull_trigger_date')
      : 'LastModifiedDate',
      '#options' => $this->get_pull_trigger_options($salesforce_object_type),
    ];

    // @TODO either change sync_triggers to human readable values, or make them work as hex flags again.
    $trigger_options = $this->get_sync_trigger_options();
    $form['sync_triggers'] = [
      '#title' => t('Action triggers'),
      '#type' => 'container',
      '#tree' => TRUE,
      '#description' => t('Select which actions on Drupal entities and Salesforce
        objects should trigger a synchronization. These settings are used by the
        salesforce_push and salesforce_pull modules.'
      ),
    ];

    foreach ($trigger_options as $option => $label) {
      $form['sync_triggers'][$option] = [
        '#title' => $label,
        '#type' => 'checkbox',
        '#default_value' => @$mapping->get('sync_triggers')[$option],
      ];
    }

    // Hide all the hidden stuff in here.
    foreach (['weight', 'status', 'locked', 'type'] as $el) {
      $form[$el] = [
        '#type' => 'value',
        '#value' => $mapping->get($el),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Fudge the Date Modified form values to get validation to pass on submit.
    if (!empty($form_state->isSubmitted())) {
      $form['salesforce_object']['pull_trigger_date']['#options'] = $this->get_pull_trigger_options($form_state->getValue('salesforce_object_type'));
    }
    parent::validateForm($form, $form_state);

    $entity_type = $form_state->getValue('drupal_entity_type');
    if (!empty($entity_type) && empty($form_state->getValue('drupal_bundle')[$entity_type])) {
      $element = &$form['drupal_entity']['drupal_bundle'][$entity_type];
      // @TODO replace with Dependency Injection
      \Drupal::formBuilder()->setError($element, $this->t('!name field is required.', ['!name' => $element['#title']]));
    }

    // In case the form was submitted without javascript, we must validate the
    // salesforce record type.
    if (empty($form_state->getValue('salesforce_record_type'))) {
      $record_types = $this->get_salesforce_record_type_options($form_state->getValue('salesforce_object_type'), $form_state);
      if (count($record_types) > 1) {
        $element = &$form['salesforce_object']['salesforce_record_type'];
        drupal_set_message($this->t('!name field is required for this Salesforce Object type.', ['!name' => $element['#title']]));
        $form_state->setValue('rebuild', TRUE);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Drupal bundle is still an array, but needs to be a string.
    $entity_type = $this->entity->get('drupal_entity_type');
    $bundle = $form_state->getValue('drupal_bundle')[$entity_type];
    $this->entity->set('drupal_bundle', $bundle);
    parent::save($form, $form_state);
  }

  /**
   *
   */
  public function drupal_entity_type_bundle_callback($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // Requires updating itself and the field map.
    $response->addCommand(new ReplaceCommand('#edit-salesforce-object', render($form['salesforce_object'])))->addCommand(new ReplaceCommand('#edit-salesforce-field-mappings-wrapper', render($form['salesforce_field_mappings_wrapper'])));
    return $response;
  }

  /**
   * Ajax callback for salesforce_mapping_form() salesforce record type.
   */
  public function salesforce_record_type_callback($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // Set the trigger options based on the selected object.
    $form['salesforce_object']['pull_trigger_date']['#options'] = $this->get_pull_trigger_options($form_state->getValue('salesforce_object_type'));
    // Requires updating itself and the field map.
    $response->addCommand(new ReplaceCommand('#edit-salesforce-object', render($form['salesforce_object'])))->addCommand(new ReplaceCommand('#edit-salesforce-field-mappings-wrapper', render($form['salesforce_field_mappings_wrapper'])));
    return $response;
  }

  /**
   * Ajax callback for salesforce_mapping_form() field CRUD.
   */
  public function field_callback($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#edit-salesforce-field-mappings-wrapper', render($form['salesforce_field_mappings_wrapper'])));
    return $response;
  }

  /**
   * Return a list of Drupal entity types for mapping.
   *
   * @return array
   *   An array of values keyed by machine name of the entity with the label as
   *   the value, formatted to be appropriate as a value for #options.
   */
  protected function get_entity_type_options() {
    $options = [];
    $entity_info = \Drupal::entityTypeManager()->getDefinitions();

    // For now, let's only concern ourselves with fieldable entities. This is an
    // arbitrary restriction, but otherwise there would be dozens of entities,
    // making this options list unwieldy.
    foreach ($entity_info as $info) {
      if (
        !in_array('Drupal\Core\Entity\ContentEntityTypeInterface', class_implements($info)) ||
        $info->id() == 'salesforce_mapped_object'
      ) {
        continue;
      }
      $options[$info->id()] = $info->getLabel();
    }
    uasort($options, function ($a, $b) {
      return strcmp($a->render(), $b->render());
    });
    return $options;
  }

  /**
   * Helper to retreive a list of object type options.
   *
   * @param array $form_state
   *   Current state of the form to store and retreive results from to minimize
   *   the need for recalculation.
   *
   * @return array
   *   An array of values keyed by machine name of the object with the label as
   *   the value, formatted to be appropriate as a value for #options.
   */
  protected function get_salesforce_object_type_options() {
    $sfobject_options = [];
    // No need to cache here: Salesforce::objects() implements its own caching.
    $sfapi = salesforce_get_api();
    // Note that we're filtering SF object types to a reasonable subset.
    $sfobjects = $sfapi->objects([
      'updateable' => TRUE,
      'triggerable' => TRUE,
    ]);
    foreach ($sfobjects as $object) {
      $sfobject_options[$object['name']] = $object['label'];
    }
    return $sfobject_options;
  }

  /**
   * Helper to retreive a list of record type options for a given object type.
   *
   * @param string $salesforce_object_type
   *   The object type of whose records you want to retreive.
   * @param array $form_state
   *   Current state of the form to store and retreive results from to minimize
   *   the need for recalculation.
   *
   * @return array
   *   An array of values keyed by machine name of the record with the label as
   *   the value, formatted to be appropriate as a value for #options.
   */
  protected function get_salesforce_record_type_options($salesforce_object_type) {
    $sf_types = [];
    $sfobject = $this->get_salesforce_object($salesforce_object_type);
    if ($sfobject && isset($sfobject['recordTypeInfos'])) {
      foreach ($sfobject['recordTypeInfos'] as $type) {
        $sf_types[$type['recordTypeId']] = $type['name'];
      }
    }
    return $sf_types;
  }

  /**
   * Return form options for available sync triggers.
   *
   * @return array
   *   Array of sync trigger options keyed by their machine name with their
   *   label as the value.
   */
  protected function get_sync_trigger_options() {
    return [
      SALESFORCE_MAPPING_SYNC_DRUPAL_CREATE => t('Drupal entity create'),
      SALESFORCE_MAPPING_SYNC_DRUPAL_UPDATE => t('Drupal entity update'),
      SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE => t('Drupal entity delete'),
      SALESFORCE_MAPPING_SYNC_SF_CREATE => t('Salesforce object create'),
      SALESFORCE_MAPPING_SYNC_SF_UPDATE => t('Salesforce object update'),
      SALESFORCE_MAPPING_SYNC_SF_DELETE => t('Salesforce object delete'),
    ];
  }

  /**
   * Helper function which returns an array of Date fields suitable for use a
   * pull trigger field.
   *
   * @param string $name
   *
   * @return array
   */
  private function get_pull_trigger_options($name) {
    $options = [];
    $describe = $this->get_salesforce_object();
    if ($describe) {
      foreach ($describe['fields'] as $field) {
        if ($field['type'] == 'datetime') {
          $options[$field['name']] = $field['label'];
        }
      }
    }
    return $options;
  }

  /**
   *
   */
  protected function get_push_plugin_options() {
    return [];
    // $field_plugins = $this->pushPluginManager->getDefinitions();
    $field_type_options = [];
    foreach ($field_plugins as $field_plugin) {
      $field_type_options[$field_plugin['id']] = $field_plugin['label'];
    }
    return $field_type_options;
  }

}
