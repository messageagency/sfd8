<?php

namespace Drupal\salesforce\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Entity form for salesforce_auth.
 */
class SalesforceAuthForm extends EntityForm {

  /**
   * The config entity.
   *
   * @var \Drupal\salesforce\Entity\SalesforceAuthConfig
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $auth = $this->entity;
    if (empty($auth->getPluginsAsOptions())) {
      $this->messenger()->addError('No auth provider plugins found. Please enable an auth provider module, e.g. salesforce_jwt, before adding an auth config.');
      $form['#access'] = FALSE;
      return $form;
    }
    $form_state->setBuildInfo($form_state->getBuildInfo()
      + ['auth_config' => $this->config($auth->getConfigDependencyName())]);
    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#description' => $this->t('User-facing label for this project, e.g. "OAuth Full Sandbox"'),
      '#default_value' => $auth->label(),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $auth->id(),
      '#maxlength' => 32,
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['label'],
      ],
      '#required' => TRUE,
    ];

    // This is the element that contains all of the dynamic parts of the form.
    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#open' => TRUE,
    ];

    $form['settings']['provider'] = [
      '#type' => 'select',
      '#title' => $this->t('Auth provider'),
      '#options' => $auth->getPluginsAsOptions(),
      '#required' => TRUE,
      '#default_value' => $auth->getPluginId(),
      '#ajax' => [
        'callback' => [$this, 'ajaxUpdateSettings'],
        'event' => 'change',
        'wrapper' => 'auth-settings',
      ],
    ];
    $default = [
      '#type' => 'container',
      '#title' => $this->t('Auth provider settings'),
      '#title_display' => FALSE,
      '#tree' => TRUE,
      '#prefix' => '<div id="auth-settings">',
      '#suffix' => '</div>',
    ];
    $form['settings']['provider_settings'] = $default;
    if ($auth->getPlugin() && !$form_state->isRebuilding()) {
      $form['settings']['provider_settings'] += $auth->getPlugin()
        ->buildConfigurationForm([], $form_state);
    }
    elseif ($form_state->getValue('provider')) {
      $plugin = $this->entity->authManager()->createInstance($form_state->getValue('provider'));
      $form['settings']['provider_settings'] += $plugin->buildConfigurationForm([], $form_state);
    }
    elseif ($form_state->getUserInput()) {
      $input = $form_state->getUserInput();
      if (!empty($input['provider'])) {
        $plugin = $this->entity->authManager()
          ->createInstance($input['provider']);
        $form['settings']['provider_settings'] += $plugin->buildConfigurationForm([], $form_state);
      }
    }
    $form['save_default'] = [
      '#type' => 'checkbox',
      '#title' => 'Save and set default',
      '#default_value' => $this->entity->isNew() || ($this->entity->authManager()->getProvider() && $this->entity->authManager()->getProvider()->id() == $this->entity->id()),
    ];
    return parent::form($form, $form_state);
  }

  /**
   * AJAX callback to update the dynamic settings on the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The element to update in the form.
   */
  public function ajaxUpdateSettings(array &$form, FormStateInterface $form_state) {
    return $form['settings']['provider_settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if (!$form_state->isSubmitted()) {
      return;
    }

    if (!empty($form_state->getErrors())) {
      // Don't bother processing plugin validation if we already have errors.
      return;
    }

    $this->entity->getPlugin()->validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->entity->getPlugin()->submitConfigurationform($form, $form_state);
    // If redirect is not already set, and we have no errors, send user back to
    // the AuthConfig listing page.
    if (!$form_state->getErrors() && !$form_state->getRedirect()) {
      $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $this->entity->getPlugin()->save($form, $form_state);
    if ($form_state->getValue('save_default')) {
      $this
        ->configFactory()
        ->getEditable('salesforce.settings')
        ->set('salesforce_auth_provider', $this->entity->id())
        ->save();
    }
  }

  /**
   * Determines if the config already exists.
   *
   * @param string $id
   *   The config ID.
   *
   * @return bool
   *   TRUE if the config exists, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function exists($id) {
    $action = \Drupal::entityTypeManager()->getStorage($this->entity->getEntityTypeId())->load($id);
    return !empty($action);
  }

}
