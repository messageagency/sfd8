<?php

namespace Drupal\salesforce_mapping\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\salesforce_mapping\SalesforceMappingFieldPluginInterface as FieldPluginInterface;

/**
 * Salesforce Mapping Fields Form.
 */
class SalesforceMappingFieldsForm extends SalesforceMappingFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!$this->ensureConnection('objectDescribe', $this->entity->getSalesforceObjectType())) {
      return $form;
    }
    $form = parent::buildForm($form, $form_state);
    // Previously "Field Mapping" table on the map edit form.
    // @TODO add a header with Fieldmap Property information.

    // Add #entity property to expose it to our field plugin forms.
    $form['#entity'] = $this->entity;

    $form['#attached']['library'][] = 'salesforce/admin';
    // This needs to be loaded now as it can't be loaded via AJAX for the AC
    // enabled fields.
    $form['#attached']['library'][] = 'core/drupal.autocomplete';

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
      $form['key_wrapper']['always_upsert'] = [
        '#type' => 'checkbox',
        '#title' => t('Always Upsert'),
        '#default_value' => $this->entity->get('always_upsert'),
        '#description' => t('If checked, always use "upsert" to push data to Salesforce. Otherwise, prefer a Salesforce ID if available. For example, given a user mapping with "email" set for upsert key, leave this checkbox off; otherwise, a new Salesforce record will be created whenever a user changes their email.'),
      ];
    }

    $form['field_mappings_wrapper'] = [
      '#title' => t('Mapped Fields'),
      '#type' => 'details',
      '#id' => 'edit-field-mappings-wrapper',
      '#open' => TRUE,
    ];

    $field_mappings_wrapper = &$form['field_mappings_wrapper'];
    // Check to see if we have enough information to allow mapping fields.  If
    // not, tell the user what is needed in order to have the field map show up.
    $field_mappings_wrapper['field_mappings'] = [
      '#tree' => TRUE,
      '#type' => 'container',
      // @TODO there's probably a better way to tie ajax callbacks to this element than by hard-coding an HTML DOM ID here.
      '#id' => 'edit-field-mappings',
      '#attributes' => ['class' => ['container-striped']],
    ];
    $rows = &$field_mappings_wrapper['field_mappings'];

    $form['field_mappings_wrapper']['ajax_warning'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'edit-ajax-warning',
      ],
    ];

    $add_field_text = !empty($field_mappings) ? t('Add another field mapping') : t('Add a field mapping to get started');

    $form['buttons'] = ['#type' => 'container'];
    $form['buttons']['field_type'] = [
      '#title' => t('Field Type'),
      '#type' => 'select',
      '#options' => $this->getDrupalTypeOptions($this->entity),
      '#attributes' => ['id' => 'edit-mapping-add-field-type'],
      '#empty_option' => $this->t('- Select -'),
    ];
    $form['buttons']['add'] = [
      '#value' => $add_field_text,
      '#type' => 'submit',
      '#executes_submit_callback' => FALSE,
      '#limit_validation_errors' => [],
      '#ajax' => [
        'callback' => [$this, 'fieldAddCallback'],
        'wrapper' => 'edit-field-mappings-wrapper',
      ],
      '#states' => [
        'disabled' => [
          ':input#edit-mapping-add-field-type' => ['value' => ''],
        ],
      ],
    ];

    $row_template = [
      '#type' => 'container',
      '#attributes' => ['class' => ['field_mapping_field', 'row']],
    ];

    // Add a row for each saved mapping.
    $zebra = 0;
    foreach ($this->entity->getFieldMappings() as $field_plugin) {
      $row = $row_template;
      $row['#attributes']['class']['zebra'] = ($zebra % 2) ? 'odd' : 'even';
      $rows[] = $row + $this->getRow($field_plugin, $form, $form_state);
      $zebra++;
    }

    // Apply any changes from form_state to existing fields.
    $input = $form_state->getUserInput();
    if (!empty($input['field_mappings'])) {
      for ($i = count($this->entity->getFieldMappings()); $i < count($input['field_mappings']); $i++) {
        $row = $row_template;
        $row['#attributes']['class']['zebra'] = ($zebra % 2) ? 'odd' : 'even';
        $field_plugin = $this->entity->getFieldMapping($input['field_mappings'][$i]);
        $rows[] = $row + $this->getRow($field_plugin, $form, $form_state);
        $zebra++;
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
    && !empty($input['field_type'])
    && $form_state->getTriggeringElement()['#name'] != 'context_drupal_field_value') {
      $row = $row_template;
      $row['#attributes']['class']['zebra'] = ($zebra % 2) ? 'odd' : 'even';
      $rows[] = $row + $this->getRow(NULL, $form, $form_state);
      $zebra++;
    }

    // Retrieve and add the form actions array.
    $actions = $this->actionsElement($form, $form_state);
    if (!empty($actions)) {
      $form['actions'] = $actions;
    }

    return $form;
  }

  /**
   * Return an options array of field labels for any fields marked externalId.
   */
  private function getUpsertKeyOptions() {
    $options = [];
    try {
      $describe = $this->getSalesforceObject();
    }
    catch (\Exception $e) {
      return [];
    }

    foreach ($describe->fields as $field) {
      if ($field['externalId'] || $field['idLookup']) {
        $options[$field['name']] = $field['label'];
      }
    }
    return $options;
  }

  /**
   * Helper function to return an empty row for the field mapping form.
   */
  private function getRow(FieldPluginInterface $field_plugin = NULL, $form, FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    if ($field_plugin == NULL) {
      $field_type = $input['field_type'];
      $field_plugin_definition = $this->getFieldPlugin($field_type);
      $field_plugin = $this->mappingFieldPluginManager->createInstance(
        $field_plugin_definition['id']
      );
    }

    $row['config'] = $field_plugin->buildConfigurationForm($form, $form_state);

    // @TODO implement "lock/unlock" logic here:
    // @TODO convert these to AJAX operations
    $operations = [
      'delete' => $this->t('Delete'),
    ];
    $defaults = [];
    $row['ops'] = [
      '#title' => t('Operations'),
      '#type' => 'checkboxes',
      '#options' => $operations,
      '#default_value' => $defaults,
    ];
    $row['drupal_field_type'] = [
      '#type' => 'hidden',
      '#value' => $field_plugin->getPluginId(),
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
    if (empty($values['field_mappings'])) {
      // No mappings have been added, no validation to be done.
      return;
    }

    $key = $values['key'];
    $key_mapped = FALSE;

    foreach ($values['field_mappings'] as $i => $value) {
      // If a field was deleted, delete it!
      if (!empty($value['ops']['delete'])) {
        $form_state->unsetValue(["field_mappings", "$i"]);
        continue;
      }

      // Pass validation to field plugins before performing mapping validation.
      $field_plugin = $this->entity->getFieldMapping($value);
      $sub_form_state = SubformState::createForSubform($form['field_mappings_wrapper']['field_mappings'][$i], $form, $form_state);
      $field_plugin->validateConfigurationForm($form['field_mappings_wrapper']['field_mappings'][$i], $sub_form_state);

      // Send to drupal field plugin for additional validation.
      if ($field_plugin->config('salesforce_field') == $key) {
        $key_mapped = TRUE;
      }
    }

    if (!empty($key) && !$key_mapped) {
      // Do not allow saving mapping when key field is not mapped.
      $form_state->setErrorByName('key', t('You must add the selected field to the field mapping in order set an Upsert Key.'));
    }

  }

  /**
   * Submit handler.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Need to transform the schema slightly to remove the "config" dereference.
    // Also trigger submit handlers on plugins.
    $form_state->unsetValue(['field_type', 'ops']);

    $values = &$form_state->getValues();
    foreach ($values['field_mappings'] as $i => &$value) {
      // Pass submit values to plugin submit handler.
      $field_plugin = $this->entity->getFieldMapping($value);
      $sub_form_state = SubformState::createForSubform($form['field_mappings_wrapper']['field_mappings'][$i], $form, $form_state);
      $field_plugin->submitConfigurationForm($form['field_mappings_wrapper']['field_mappings'][$i], $sub_form_state);

      $value = $value + $value['config'];
      unset($value['config'], $value['ops']);
    }
    parent::submitForm($form, $form_state);
  }

  /**
   * Ajax callback for adding a new field.
   */
  public function fieldAddCallback($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // Requires updating itself and the field map.
    $response->addCommand(new ReplaceCommand('#edit-field-mappings-wrapper', render($form['field_mappings_wrapper'])));
    return $response;
  }

  /**
   * Get an array of drupal types.
   */
  protected function getDrupalTypeOptions($mapping) {
    $field_plugins = $this->mappingFieldPluginManager->getDefinitions();
    $options = [];
    foreach ($field_plugins as $definition) {
      if (call_user_func([$definition['class'], 'isAllowed'], $mapping)) {
        $options[$definition['id']] = $definition['label'];
      }
    }
    return $options;
  }

  /**
   * Get a field plugin of the given type.
   */
  protected function getFieldPlugin($field_type) {
    $field_plugins = $this->mappingFieldPluginManager->getDefinitions();
    return $field_plugins[$field_type];
  }

}
