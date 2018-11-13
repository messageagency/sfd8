<?php

namespace Drupal\salesforce\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;

/**
 * Defines a Salesforce Auth entity.
 *
 * @ConfigEntityType(
 *   id = "salesforce_auth",
 *   label = @Translation("Salesforce Auth Config"),
 *   module = "salesforce_auth",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\salesforce\Controller\SalesforceAuthListBuilder",
 *     "form" = {
 *       "default" = "Drupal\salesforce\Form\SalesforceAuthForm",
 *       "delete" = "Drupal\salesforce\Form\SalesforceAuthDeleteForm",
 *       "revoke" = "Drupal\salesforce\Form\SalesforceAuthRevokeForm"
 *      }
 *   },
 *   links = {
 *     "collection" = "/admin/config/salesforce/authorize/list",
 *     "edit-form" = "/admin/config/salesforce/authorize/edit/{salesforce_auth}",
 *     "delete-form" = "/admin/config/salesforce/authorize/delete/{salesforce_auth}",
 *     "revoke" = "/admin/config/salesforce/authorize/revoke/{salesforce_auth}"
 *   },
 *   admin_permission = "authorize salesforce",
 * )
 */
class SalesforceAuthConfig extends ConfigEntityBase implements EntityInterface {

  /**
   * Auth id. e.g. "oauth_full_sandbox".
   *
   * @var string
   */
  protected $id;

  /**
   * Auth label. e.g. "OAuth Full Sandbox".
   *
   * @var string
   */
  protected $label;

  /**
   * @var \Drupal\salesforce\SalesforceAuthProviderPluginInterface
   */
  protected $provider;

  protected $provider_settings = [];

  /**
   * Auth manager.
   *
   * @var \Drupal\salesforce\SalesforceAuthProviderPluginManager
   */
  protected $manager;

  /**
   * Id getter.
   */
  public function id() {
    return $this->id;
  }

  /**
   * Label getter.
   */
  public function label() {
    return $this->label;
  }

  /**
   * Plugin getter.
   *
   * @return \Drupal\salesforce\SalesforceAuthProviderInterface
   */
  public function getPlugin() {
    $settings = $this->provider_settings ?: [];
    $settings += ['id' => $this->id()];
    return $this->provider ? $this->authManager()->createInstance($this->provider, $settings) : NULL;
  }

  public function getPluginId() {
    return $this->provider ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getLoginUrl() {
    return $this->getPlugin() ? $this->getPlugin()->getLoginUrl() : '';
  }

  /**
   * Auth manager wrapper.
   *
   * @return \Drupal\salesforce\SalesforceAuthProviderPluginManager|mixed
   */
  public function authManager() {
    if (!$this->manager) {
      $this->manager = \Drupal::service("plugin.manager.salesforce.auth_providers");
    }
    return $this->manager;
  }

  /**
   * Returns a list of plugins, for use in forms.
   *
   * @param string $type
   *   The plugin type to use.
   *
   * @return array
   *   The list of plugins, indexed by ID.
   */
  public function getPluginsAsOptions() {
    $options = ['' => t('- Select -')];
    foreach ($this->authManager()->getDefinitions() as $id => $definition) {
      $options[$id] = ($definition['label']);
    }

    return $options;
  }

}