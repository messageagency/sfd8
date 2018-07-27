<?php

namespace Drupal\salesforce_auth\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce_auth\Plugin\SalesforceAuthProviderFormInterface;
use Drupal\salesforce_oauth\Entity\OAuthConfig;

/**
 * Entity form for JWT Auth Config.
 */
class SalesforceAuthForm extends EntityForm {

  /**
   * The config entity.
   *
   * @var \Drupal\salesforce_auth\Entity\SalesforceAuth
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $auth = $this->entity;

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#description' => $this->t('User-facing label for this project, e.g. "Full Sandbox"'),
      '#default_value' => $auth->label(),
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $auth->id(),
      '#maxlength' => 32,
      '#machine_name' => [
        'exists' => [$this, 'exists'],
        'source' => ['label'],
      ],
    ];

    // This is the element that contains all of the dynamic parts of the form.
    $form['settings'] = [
      '#type' => 'container',
      '#prefix' => '<div id="auth-settings">',
      '#suffix' => '</div>',
    ];

    // Key type section.
    $form['settings']['provider_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#open' => TRUE,
    ];
    $form['settings']['provider_section']['auth_provider'] = [
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
    $form['settings']['provider_section']['key_provider_settings'] = [
      '#type' => 'container',
      '#title' => $this->t('Auth provider settings'),
      '#title_display' => FALSE,
      '#tree' => TRUE,
    ];

    if ($auth->getPlugin()) {
      $form['settings']['provider_section']['key_provider_settings'] += $auth->getPlugin()
        ->buildConfigurationForm([], $form_state);
    }

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
    return $form['settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
  }

  /**
   * Determines if the config already exists.
   *
   * @param string $id
   *   The config ID.
   *
   * @return bool
   *   TRUE if the config exists, FALSE otherwise.
   */
  public function exists($id) {
    $action = \Drupal::entityTypeManager()->getStorage($this->entity->getEntityTypeId())->load($id);
    return !empty($action);
  }

}
