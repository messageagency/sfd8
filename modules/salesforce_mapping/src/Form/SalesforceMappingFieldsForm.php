<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Form\SalesforceMappingFieldsForm.
 */

namespace Drupal\salesforce_mapping\Form;

use Symfony\Component\Debug\Debug;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface as FieldPluginInterface;

/**
 * Salesforce Mapping Fields Form
 */
class SalesforceMappingFieldsForm extends SalesforceMappingFormBase {

  /**
   * Previously "Field Mapping" table on the map edit form.
   * {@inheritdoc}
   * @TODO add a header with Fieldmap Property information
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#entity'] = $this->entity;
    // For each field on the map, add a row to our table.
    $form['overview'] = ['#markup' => 'Field mapping overview goes here.'];

    $form['key_wrapper'] = [
      '#title' => t('Upsert Key'),
      '#type' => 'details',
      '#open' => TRUE,
      '#description' => t('An Upsert Key can be assigned to map a Drupal property to a Salesforce External Identifier. If specified an UPSERT will be used to limit data duplication.'),
    ];

    $key_options = $this->getUpsertKeyOptions();
    if (empty($key_options)) {
      $form['key_wrapper']['#description'] .= ' ' . t('To add an upsert key for @sobject_name, assign a field as an External Identifier in Salesforce.', ['@sobject_name' => $this->entity->get('salesforce_object_type')]);
      $form['key_wrapper']['key'] = [
        '#type' => 'value',
        '#value' => '',
      ];
    }
    else {
      $form['key_wrapper']['key'] = [
        '#type' => 'select',
        '#title' => t('Upsert Key'),
        '#options' => $key_options,
        '#default_value' => $this->entity->getKeyField(),
        '#empty_option' => t('(none)'),
        '#empty_value' => '',
      ];
    }

    $form['field_mappings_wrapper'] = [
      '#title' => t('Field map'),
      '#type' => 'details',
      '#id' => 'edit-field-mappings-wrapper',
      '#open' => TRUE,
    ];

    $field_mappings_wrapper = &$form['field_mappings_wrapper'];
    // Check to see if we have enough information to allow mapping fields.  If
    // not, tell the user what is needed in order to have the field map show up.

    $field_mappings_wrapper['field_mappings'] = [
      '#tree' => TRUE,
      '#type' => 'table',
      // @TODO there's probably a better way to tie ajax callbacks to this element than by hard-coding an HTML DOM ID here.
      '#id' => 'edit-field-mappings',
      '#header' => [
        // @TODO: there must be a better way to get two fields in the same cell than to create an extraneous column
        'drupal_field_type' => '',
        'drupal_field_type_label' => $this->t('Field type'),
        'drupal_field_value' => $this->t('Drupal field'),
        'salesforce_field' => $this->t('Salesforce field'),
        'direction' => $this->t('Direction'),
        'ops' => $this->t('Operations'),
      ],
    ];
    $rows = &$field_mappings_wrapper['field_mappings'];

    $form['field_mappings_wrapper']['ajax_warning'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'edit-ajax-warning',
      ],
    ];

    // @TODO figure out how D8 does tokens
    // $form['field_mappings_wrapper']['token_tree'] = array(
    //   '#type' => 'container',
    //   '#attributes' => array(
    //     'id' => array('edit-token-tree'),
    //   ),
    // );
    // $form['field_mappings_wrapper']['token_tree']['tree'] = array(
    //   '#theme' => 'token_tree',
    //   '#token_types' => array($drupal_entity_type),
    //   '#global_types' => TRUE,
    // );
    $add_field_text = !empty($field_mappings) ? t('Add another field mapping') : t('Add a field mapping to get started');

  
    $form['buttons'] = ['#type' => 'container'];
    $form['buttons']['field_type'] = [
      '#title' => t('Field Type'),
      '#type' => 'select',
      '#options' => $this->get_drupal_type_options(),
      '#attributes' => ['id' => 'edit-mapping-add-field-type'],
      '#empty_option' => $this->t('- Select -'),
    ];
    $form['buttons']['add'] = [
      '#value' => $add_field_text,
      '#type' => 'submit',
      '#executes_submit_callback' => FALSE,
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [$this, 'field_add_callback'],
        'wrapper' => 'edit-field-mappings-wrapper',
      ],
      // @TODO add validation to field_add_callback()
      '#states' => [
        'disabled' => [
          ':input#edit-mapping-add-field-type' => ['value' => ''],
        ],
      ],
    ];

    // Field mapping form.
    $has_token_type = FALSE;

    // Add a row for each saved mapping
    $delta = 0;
    foreach ($this->entity->getFieldMappings() as $field_plugin) {
      $value['delta'] = $delta;
      $rows[$delta] = $this->get_row($field_plugin, $form, $form_state);
      $delta++;
    }

    // Apply any changes from form_state to existing fields.
    $input = $form_state->getUserInput();
    if (!empty($input['field_mappings'])) {
      while (isset($input['field_mappings'][++$delta])) {
        $rows[$delta] = $this->get_row($input['field_mappings'][$delta], $form, $form_state);
      }
    }
    // @TODO input does not contain the clicked button, have to go to values for
    // that. This may change?

    // Add button was clicked. See if we have a field_type value -- it's
    // required. If not, take no action. #states is already used to prevent
    // users from adding without selecting field_type. If they've worked
    // around that, they're going to have problems.
    if (!empty($form_state->getValues())
    && $form_state->getValue('add') == $form_state->getValue('op')
    && !empty($input['field_type'])) {
      $rows[$delta] = $this->get_row(NULL, $form, $form_state);
    }

    // Retrieve and add the form actions array.
    $actions = $this->actionsElement($form, $form_state);
    if (!empty($actions)) {
      $form['actions'] = $actions;
    }

    return $form;
  }

  private function getUpsertKeyOptions() {
    $options = [];
    $describe = $this->get_salesforce_object();
    foreach ($describe['fields'] as $field) {
      if ($field['externalId']) {
        $options[$field['name']] = $field['label'];
      }
    }
    return $options;
  }

  /**
   * Helper function to return an empty row for the field mapping form.
   */
  private function get_row(FieldPluginInterface $field_plugin = NULL, $form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();

    if ($field_plugin != NULL) {
      $field_type = $field_plugin->config('drupal_field_type');
      $field_plugin_definition = $this->get_field_plugin($field_type);
    }
    else {
      $field_plugin_definition = $field_type = NULL;
      $field_type = $input['field_type'];
      $field_plugin_definition = $this->get_field_plugin($field_type);
      $field_plugin = $this->SalesforceMappingFieldManager->createInstance(
        $field_plugin_definition['id']
      );      
    }

    if (empty($field_type)) {
      // @TODO throw an exception here ?
      return;
    }

    if (empty($field_plugin_definition)) {
      // @TODO throw an exception here ?
      return;
    }

    // @TODO allow plugins to override forms for all these fields
    $row['drupal_field_type'] = [
        '#type' => 'hidden',
        '#value' => $field_type,
    ];
    $row['drupal_field_type_label'] = [
        '#markup' => $field_plugin_definition['label'],
    ];

    // Display the plugin config form here:
    $row['drupal_field_value'] = $field_plugin->buildConfigurationForm($form, $form_state);

    $row['salesforce_field'] = [
      '#type' => 'select',
      '#description' => t('Select a Salesforce field to map.'),
      '#multiple' => (isset($drupal_field_type['salesforce_multiple_fields']) && $drupal_field_type['salesforce_multiple_fields']) ? TRUE : FALSE,
      '#options' => $this->get_salesforce_field_options(),
      '#default_value' => $field_plugin->config('salesforce_field'),
      '#empty_option' => $this->t('- Select -'),
    ];

    $row['direction'] = [
      '#type' => 'radios',
      '#options' => [
        SALESFORCE_MAPPING_DIRECTION_DRUPAL_SF => t('Drupal to SF'),
        SALESFORCE_MAPPING_DIRECTION_SF_DRUPAL => t('SF to Drupal'),
        SALESFORCE_MAPPING_DIRECTION_SYNC => t('Sync'),
      ],
      '#required' => TRUE,
      '#default_value' => $field_plugin->config('direction') ? $field_plugin->config('direction') : SALESFORCE_MAPPING_DIRECTION_SYNC,
    ];

    // @TODO implement "lock/unlock" logic here:
    // @TODO convert these to AJAX operations
    $operations = [
      'locked' => $this->t('Lock'),
      'delete' => $this->t('Delete')
    ];
    $defaults = [];
    if ($field_plugin->config('locked')) {
      $defaults = ['lock'];
    }
    $row['ops'] = [
      '#type' => 'checkboxes',
      '#options' => $operations,
      '#default_value' => $defaults,
    ];
    return $row;
  }

 /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Transform data from the operations column into the expected schema.
    // Copy the submitted values so we don't run into problems with array
    // indexing while removing delete field mappings.

    $values = $form_state->getValues();
    $key = $values['key'];
    $key_mapped = FALSE;

    $sfobject = $this->get_salesforce_object();
    foreach ($values['field_mappings'] as $i => $value) {
      if ($value['salesforce_field'] == $key) {
        $key_mapped = TRUE;
      }
      // If a field was deleted, delete it!
      if (!empty($value['ops']['delete'])) {
        $form_state->unsetValue(["field_mappings", "$i"]);
        continue;
      }
      $values['field_mappings'][$i]['locked'] = !empty($value['ops']['lock']);

      // Remove UI crud from form state array:
      $form_state->unsetValue(['field_mappings', $i, 'ops']);
      $form_state->unsetValue('field_type');
    }

    if (!empty($key) && !$key_mapped) {
      // Do not allow saving mapping when key field is not mapped.
      $form_state->setErrorByName('key', t('You must add the selected field to the field mapping in order set an Upsert Key.'));
    }

  }

  public function field_add_callback($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // Requires updating itself and the field map.
    $response->addCommand(new ReplaceCommand('#edit-field-mappings-wrapper', render($form['field_mappings_wrapper'])));
    return $response;
  }

  protected function get_drupal_type_options() {
    $field_plugins = $this->SalesforceMappingFieldManager->getDefinitions();
    $field_type_options = [];
    foreach ($field_plugins as $field_plugin) {
      $field_type_options[$field_plugin['id']] = $field_plugin['label'];
    }
    return $field_type_options;
  }

  protected function get_field_plugin($field_type) {
    // @TODO not sure if it's best practice to static cache definitions, or just
    // get them from SalesforceMappingFieldManager each time.
    $field_plugins = $this->SalesforceMappingFieldManager->getDefinitions();
    return $field_plugins[$field_type];
  }

  /**
   * Helper to retreive a list of fields for a given object type.
   *
   * @param string $salesforce_object_type
   *   The object type of whose fields you want to retreive.
   *
   * @return array
   *   An array of values keyed by machine name of the field with the label as
   *   the value, formatted to be appropriate as a value for #options.
   */
  protected function get_salesforce_field_options() {
    $sfobject = $this->get_salesforce_object();
    $sf_fields = [];
    if (isset($sfobject['fields'])) {
      foreach ($sfobject['fields'] as $sf_field) {
        $sf_fields[$sf_field['name']] = $sf_field['label'];
      }
    }
    asort($sf_fields);
    return $sf_fields;
  }

}
