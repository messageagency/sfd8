<?php

namespace Drupal\salesforce_mapping_ui\Form;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\Core\Url;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Event\SalesforceErrorEvent;

/**
 * Salesforce Mapping Form base.
 */
abstract class SalesforceMappingFormCrudBase extends SalesforceMappingFormBase {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if (!$this->ensureConnection()) {
      return $form;
    }

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
      '#maxlength' => EntityTypeInterface::ID_MAX_LENGTH,
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

    $entity_types = $this->getEntityTypeOptions();
    $form['drupal_entity']['drupal_entity_type'] = [
      '#title' => $this->t('Drupal Entity Type'),
      '#id' => 'edit-drupal-entity-type',
      '#type' => 'select',
      '#description' => $this->t('Select a Drupal entity type to map to a Salesforce object.'),
      '#options' => $entity_types,
      '#default_value' => $mapping->drupal_entity_type,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
      '#ajax' => [
        'callback' => [$this, 'bundleCallback'],
        'event' => 'change',
        'wrapper' => 'drupal_bundle',
      ],
    ];

    $form['drupal_entity']['drupal_bundle'] = [
      '#title' => $this->t('Bundle'),
      '#type' => 'select',
      '#default_value' => $mapping->drupal_bundle,
      '#empty_option' => $this->t('- Select -'),
      // Bundle select options will get completely replaced after user selects
      // an entity, but we include all possibilities here for js-free
      // compatibility (for simpletest)
      '#options' => $this->getBundleOptions(),
      '#required' => TRUE,
      '#prefix' => '<div id="drupal_bundle">',
      '#suffix' => '</div>',
      // Don't expose the bundle listing until user has selected an entity.
      '#states' => [
        'visible' => [
          ':input[name="drupal_entity_type"]' => ['!value' => ''],
        ],
      ],
    ];
    $input = $form_state->getUserInput();
    if (!empty($input) && !empty($input['drupal_entity_type'])) {
      $entity_type = $input['drupal_entity_type'];
    }
    else {
      $entity_type = $form['drupal_entity']['drupal_entity_type']['#default_value'];
    }
    $bundle_info = $this->bundleInfo->getBundleInfo($entity_type);

    if (!empty($bundle_info)) {
      $form['drupal_entity']['drupal_bundle']['#options'] = [];
      $form['drupal_entity']['drupal_bundle']['#title'] = $this->t('@entity_type Bundle', ['@entity_type' => $entity_types[$entity_type]]);
      foreach ($bundle_info as $key => $info) {
        $form['drupal_entity']['drupal_bundle']['#options'][$key] = $info['label'];
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
    elseif ($mapping->salesforce_object_type) {
      $salesforce_object_type = $mapping->salesforce_object_type;
    }
    $form['salesforce_object']['salesforce_object_type'] = [
      '#title' => $this->t('Salesforce Object'),
      '#id' => 'edit-salesforce-object-type',
      '#type' => 'select',
      '#description' => $this->t('Select a Salesforce object to map.'),
      '#default_value' => $salesforce_object_type,
      '#options' => $this->getSalesforceObjectTypeOptions(),
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];

    // @TODO either change sync_triggers to human readable values, or make them work as hex flags again.
    $trigger_options = $this->getSyncTriggerOptions();
    $form['sync_triggers'] = [
      '#title' => $this->t('Action triggers'),
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => TRUE,
      '#description' => $this->t('Select which actions on Drupal entities and Salesforce
        objects should trigger a synchronization. These settings are used by the
        salesforce_push and salesforce_pull modules.'
      ),
    ];
    if (empty($trigger_options)) {
      $form['sync_triggers']['#description'] .= ' ' . $this->t('<br/><em>No trigger options are available when Salesforce Push and Pull modules are disabled. Enable one or both modules to allow Push or Pull processing.</em>');
    }

    foreach ($trigger_options as $option => $label) {
      $form['sync_triggers'][$option] = [
        '#title' => $label,
        '#type' => 'checkbox',
        '#default_value' => !empty($mapping->sync_triggers[$option]),
      ];
    }

    if ($this->moduleHandler->moduleExists('salesforce_pull')) {
      // @TODO should push and pull settings get moved into push and pull modules?
      $form['pull'] = [
        '#title' => $this->t('Pull Settings'),
        '#type' => 'details',
        '#description' => '',
        '#open' => TRUE,
        '#tree' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name^="sync_triggers[pull"]' => ['checked' => TRUE],
          ],
        ],
      ];

      if (!$mapping->isNew()) {
        $form['pull']['last_pull_date'] = [
          '#type' => 'item',
          '#title' => $this->t('Last Pull Date: %last_pull', ['%last_pull' => $mapping->getLastPullTime() ? \Drupal::service('date.formatter')->format($mapping->getLastPullTime()) : 'never']),
          '#markup' => $this->t('Resetting last pull date will cause salesforce pull module to query for updated records without respect for the pull trigger date. This is useful, for example, to re-pull all records after a purge.'),
        ];
        $form['pull']['last_pull_reset'] = [
          '#type' => 'button',
          '#value' => $this->t('Reset Last Pull Date'),
          '#disabled' => $mapping->getLastPullTime() == NULL,
          '#limit_validation_errors' => [],
          '#validate' => ['::lastPullReset'],
        ];

        $form['pull']['last_delete_date'] = [
          '#type' => 'item',
          '#title' => $this->t('Last Delete Date: %last_pull', ['%last_pull' => $mapping->getLastDeleteTime() ? \Drupal::service('date.formatter')->format($mapping->getLastDeleteTime()) : 'never']),
          '#markup' => $this->t('Resetting last delete date will cause salesforce pull module to query for deleted record without respect for the pull trigger date.'),
        ];
        $form['pull']['last_delete_reset'] = [
          '#type' => 'button',
          '#value' => $this->t('Reset Last Delete Date'),
          '#disabled' => $mapping->getLastDeleteTime() == NULL,
          '#limit_validation_errors' => [],
          '#validate' => ['::lastDeleteReset'],
        ];

        // This doesn't work until after mapping gets saved.
        // @TODO figure out best way to alert admins about this, or AJAX-ify it.
        $form['pull']['pull_trigger_date'] = [
          '#type' => 'select',
          '#title' => $this->t('Date field to trigger pull'),
          '#description' => $this->t('Poll Salesforce for updated records based on the given date field. Defaults to "Last Modified Date".'),
          '#required' => $mapping->salesforce_object_type,
          '#default_value' => $mapping->pull_trigger_date,
          '#options' => $this->getPullTriggerOptions(),
        ];
      }

      $form['pull']['pull_where_clause'] = [
        '#title' => $this->t('Pull query SOQL "Where" clause'),
        '#type' => 'textarea',
        '#description' => $this->t('Add a "where" SOQL condition clause to limit records pulled from Salesforce. e.g. Email != \'\' AND RecordType.DevelopName = \'ExampleRecordType\''),
        '#default_value' => $mapping->pull_where_clause,
      ];

      $form['pull']['pull_where_clause'] = [
        '#title' => $this->t('Pull query SOQL "Where" clause'),
        '#type' => 'textarea',
        '#description' => $this->t('Add a "where" SOQL condition clause to limit records pulled from Salesforce. e.g. Email != \'\' AND RecordType.DevelopName = \'ExampleRecordType\''),
        '#default_value' => $mapping->pull_where_clause,
      ];

      $form['pull']['pull_frequency'] = [
        '#title' => $this->t('Pull Frequency'),
        '#type' => 'number',
        '#default_value' => $mapping->pull_frequency,
        '#description' => $this->t('Enter a frequency, in seconds, for how often this mapping should be used to pull data to Drupal. Enter 0 to pull as often as possible. FYI: 1 hour = 3600; 1 day = 86400. <em>NOTE: pull frequency is shared per-Salesforce Object. The setting is exposed here for convenience.</em>'),
      ];

      $description = $this->t('Check this box to disable cron pull processing for this mapping, and allow standalone processing only. A URL will be generated after saving the mapping.');
      if ($mapping->id()) {
        $standalone_url = Url::fromRoute(
          'salesforce_pull.endpoint.salesforce_mapping',
          [
            'salesforce_mapping' => $mapping->id(),
            'key' => \Drupal::state()->get('system.cron_key'),
          ],
          ['absolute' => TRUE])
          ->toString();
        $description = $this->t('Check this box to disable cron pull processing for this mapping, and allow standalone processing via this URL: <a href=":url">:url</a>', [':url' => $standalone_url]);
      }
      $form['pull']['pull_standalone'] = [
        '#title' => $this->t('Enable standalone pull queue processing'),
        '#type' => 'checkbox',
        '#description' => $description,
        '#default_value' => $mapping->pull_standalone,
      ];

      // If global standalone is enabled, then we force this mapping's
      // standalone property to true.
      if ($this->config('salesforce.settings')->get('standalone')) {
        $settings_url = Url::fromRoute('salesforce.global_settings')->toString();
        $form['pull']['pull_standalone']['#default_value'] = TRUE;
        $form['pull']['pull_standalone']['#disabled'] = TRUE;
        $form['pull']['pull_standalone']['#description'] .= ' ' . $this->t('See also <a href="@url">global standalone processing settings</a>.', ['@url' => $settings_url]);
      }
    }

    if ($this->moduleHandler->moduleExists('salesforce_push')) {
      $form['push'] = [
        '#title' => $this->t('Push Settings'),
        '#type' => 'details',
        '#description' => $this->t('The asynchronous push queue is always enabled in Drupal 8: real-time push fails are queued for async push. Alternatively, you can choose to disable real-time push and use async-only.'),
        '#open' => TRUE,
        '#tree' => FALSE,
        '#states' => [
          'visible' => [
            ':input[name^="sync_triggers[push"]' => ['checked' => TRUE],
          ],
        ],
      ];

      $form['push']['async'] = [
        '#title' => $this->t('Disable real-time push'),
        '#type' => 'checkbox',
        '#description' => $this->t('When real-time push is disabled, enqueue changes and push to Salesforce asynchronously during cron. When disabled, push changes immediately upon entity CRUD, and only enqueue failures for async push.'),
        '#default_value' => $mapping->async,
      ];

      $form['push']['push_frequency'] = [
        '#title' => $this->t('Push Frequency'),
        '#type' => 'number',
        '#default_value' => $mapping->push_frequency,
        '#description' => $this->t('Enter a frequency, in seconds, for how often this mapping should be used to push data to Salesforce. Enter 0 to push as often as possible. FYI: 1 hour = 3600; 1 day = 86400.'),
        '#min' => 0,
      ];

      $form['push']['push_limit'] = [
        '#title' => $this->t('Push Limit'),
        '#type' => 'number',
        '#default_value' => $mapping->push_limit,
        '#description' => $this->t('Enter the maximum number of records to be pushed to Salesforce during a single queue batch. Enter 0 to process as many records as possible, subject to the global push queue limit.'),
        '#min' => 0,
      ];

      $form['push']['push_retries'] = [
        '#title' => $this->t('Push Retries'),
        '#type' => 'number',
        '#default_value' => $mapping->push_retries,
        '#description' => $this->t("Enter the maximum number of attempts to push a record to Salesforce before it's considered failed. Enter 0 for no limit."),
        '#min' => 0,
      ];

      $form['push']['weight'] = [
        '#title' => $this->t('Weight'),
        '#type' => 'select',
        '#options' => array_combine(range(-50, 50), range(-50, 50)),
        '#description' => $this->t('Not yet in use. During cron, mapping weight determines in which order items will be pushed. Lesser weight items will be pushed before greater weight items.'),
        '#default_value' => $mapping->weight,
      ];

      $description = $this->t('Check this box to disable cron push processing for this mapping, and allow standalone processing. A URL will be generated after saving the mapping.');
      if ($mapping->id()) {
        $standalone_url = Url::fromRoute(
          'salesforce_push.endpoint.salesforce_mapping',
          [
            'salesforce_mapping' => $mapping->id(),
            'key' => \Drupal::state()->get('system.cron_key'),
          ],
          ['absolute' => TRUE])
          ->toString();
        $description = $this->t('Check this box to disable cron push processing for this mapping, and allow standalone processing via this URL: <a href=":url">:url</a>', [':url' => $standalone_url]);
      }

      $form['push']['push_standalone'] = [
        '#title' => $this->t('Enable standalone push queue processing'),
        '#type' => 'checkbox',
        '#description' => $description,
        '#default_value' => $mapping->push_standalone,
      ];

      // If global standalone is enabled, then we force this mapping's
      // standalone property to true.
      if ($this->config('salesforce.settings')->get('standalone')) {
        $settings_url = Url::fromRoute('salesforce.global_settings')->toString();
        $form['push']['push_standalone']['#default_value'] = TRUE;
        $form['push']['push_standalone']['#disabled'] = TRUE;
        $form['push']['push_standalone']['#description'] .= ' ' . $this->t('See also <a href="@url">global standalone processing settings</a>.', ['@url' => $settings_url]);
      }
    }

    $form['meta'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#tree' => FALSE,
      '#title' => $this->t('Additional properties'),
    ];

    $form['meta']['weight'] = [
      '#title' => $this->t('Weight'),
      '#type' => 'select',
      '#options' => array_combine(range(-50, 50), range(-50, 50)),
      '#description' => $this->t('During cron, mapping weight determines in which order items will be pushed or pulled. Lesser weight items will be pushed or pulled before greater weight items.'),
      '#default_value' => $mapping->weight,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $bundles = $this->bundleInfo->getBundleInfo($form_state->getValue('drupal_entity_type'));
    if (empty($bundles[$form_state->getValue('drupal_bundle')])) {
      $form_state->setErrorByName('drupal_bundle', $this->t('Invalid bundle for entity type.'));
    }
    $button = $form_state->getTriggeringElement();
    if ($button['#id'] != $form['actions']['submit']['#id']) {
      // Skip validation unless we hit the "save" button.
      return;
    }

    parent::validateForm($form, $form_state);

    if ($this->entity->doesPull()) {
      try {
        $this->client->query($this->entity->getPullQuery());
      }
      catch (\Exception $e) {
        $form_state->setError($form['pull']['pull_where_clause'], $this->t('Test pull query returned an error. Please check logs for error details.'));
        \Drupal::service('event_dispatcher')->dispatch(SalesforceEvents::ERROR, new SalesforceErrorEvent($e));
      }
    }
  }

  /**
   * Submit handler for "reset pull timestamp" button.
   */
  public function lastPullReset(array $form, FormStateInterface $form_state) {
    $mapping = $this->entity->setLastPullTime(NULL);
    $this->entityTypeManager
      ->getStorage('salesforce_mapped_object')
      ->setForcePull($mapping);
  }

  /**
   * Submit handler for "reset delete timestamp" button.
   */
  public function lastDeleteReset(array $form, FormStateInterface $form_state) {
    $this->entity->setLastDeleteTime(NULL);
  }

  /**
   * Ajax callback for salesforce_mapping_form() bundle selection.
   */
  public function bundleCallback($form, FormStateInterface $form_state) {
    return $form['drupal_entity']['drupal_bundle'];
  }

  /**
   * Return an array of all bundle options, for javascript-free fallback.
   */
  protected function getBundleOptions() {
    $entities = $this->getEntityTypeOptions();
    $bundles = $this->bundleInfo->getAllBundleInfo();
    $options = [];
    foreach ($bundles as $entity => $bundle_info) {
      if (empty($entities[$entity])) {
        continue;
      }
      foreach ($bundle_info as $bundle => $info) {
        $entity_label = $entities[$entity];
        $options[(string) $entity_label][$bundle] = (string) $info['label'];
      }
    }
    return $options;
  }

  /**
   * Return a list of Drupal entity types for mapping.
   *
   * @return array
   *   An array of values keyed by machine name of the entity with the label as
   *   the value, formatted to be appropriate as a value for #options.
   */
  protected function getEntityTypeOptions() {
    $options = [];
    $mappable_entity_types = $this->mappableEntityTypes
      ->getMappableEntityTypes();
    foreach ($mappable_entity_types as $entity_type_id => $info) {
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

  /**
   * Return form options for available sync triggers.
   *
   * @return array
   *   Array of sync trigger options keyed by their machine name with their
   *   label as the value.
   */
  protected function getSyncTriggerOptions() {
    $options = [];
    if ($this->moduleHandler->moduleExists('salesforce_push')) {
      $options += [
        MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_CREATE => $this->t('Drupal entity create (push)'),
        MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_UPDATE => $this->t('Drupal entity update (push)'),
        MappingConstants::SALESFORCE_MAPPING_SYNC_DRUPAL_DELETE => $this->t('Drupal entity delete (push)'),
      ];
    }
    if ($this->moduleHandler->moduleExists('salesforce_pull')) {
      $options += [
        MappingConstants::SALESFORCE_MAPPING_SYNC_SF_CREATE => $this->t('Salesforce object create (pull)'),
        MappingConstants::SALESFORCE_MAPPING_SYNC_SF_UPDATE => $this->t('Salesforce object update (pull)'),
        MappingConstants::SALESFORCE_MAPPING_SYNC_SF_DELETE => $this->t('Salesforce object delete (pull)'),
      ];
    }
    return $options;
  }

  /**
   * Return an array of Date fields suitable for use a pull trigger field.
   *
   * @return array
   *   The options array.
   */
  private function getPullTriggerOptions() {
    $options = [];
    try {
      $describe = $this->getSalesforceObject();
    }
    catch (\Exception $e) {
      // No describe results means no datetime fields. We're done.
      return [];
    }

    foreach ($describe->getFields() as $field) {
      if ($field['type'] == 'datetime') {
        $options[$field['name']] = $field['label'];
      }
    }
    return $options;
  }

}
