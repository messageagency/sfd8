<?php

namespace Drupal\salesforce\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityWithPluginCollectionInterface;
use Drupal\Core\Plugin\DefaultSingleLazyPluginCollection;
use Drupal\Core\StringTranslation\StringTranslationTrait;

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
 *   config_export = {
 *    "id",
 *    "label",
 *    "provider",
 *    "provider_settings"
 *   },
 * )
 */
class SalesforceAuthConfig extends ConfigEntityBase implements EntityWithPluginCollectionInterface {

  use StringTranslationTrait;

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
   * The auth provider for this auth config.
   *
   * @var string
   */
  protected $provider;

  /**
   * Provider plugin configuration settings.
   *
   * @var array
   */
  protected $provider_settings = [];

  /**
   * Auth manager.
   *
   * @var \Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface
   */
  protected $manager;

  /**
   * The plugin provider.
   *
   * @var \Drupal\salesforce\SalesforceAuthProviderInterface
   */
  protected $plugin;

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
   * @return \Drupal\salesforce\SalesforceAuthProviderInterface|null
   *   The auth provider plugin, or null.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getPlugin() {
    if (!$this->plugin) {
      $this->plugin = $this->provider ? $this->authManager()->createInstance($this->provider, $this->getProviderSettings()) : NULL;
    }
    return $this->plugin;
  }

  /**
   * Wrapper for provider settings to inject instance id, from auth config.
   *
   * @return array
   *   Provider settings.
   */
  public function getProviderSettings() {
    return $this->provider_settings + ['id' => $this->id()];
  }

  /**
   * Plugin id getter.
   *
   * @return string|null
   *   The auth provider plugin id, or null.
   */
  public function getPluginId() {
    return $this->provider ?: NULL;
  }

  /**
   * Get credentials.
   *
   * @return \Drupal\salesforce\Consumer\SalesforceCredentialsInterface|false
   *   Credentials or FALSE.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getCredentials() {
    return $this->getPlugin() ? $this->getPlugin()->getCredentials() : FALSE;
  }

  /**
   * Auth manager wrapper.
   *
   * @return \Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface|mixed
   *   The auth provider plugin manager.
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
   * @return array
   *   The list of plugins, indexed by ID.
   */
  public function getPluginsAsOptions() {
    foreach ($this->authManager()->getDefinitions() as $id => $definition) {
      if ($id == 'broken') {
        // Do not add the fallback provider.
        continue;
      }
      $options[$id] = ($definition['label']);
    }
    if (!empty($options)) {
      return ['' => $this->t('- Select -')] + $options;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPluginCollections() {
    return [
      'auth_provider' => new DefaultSingleLazyPluginCollection($this->authManager(), $this->provider, $this->getProviderSettings()),
    ];
  }

}
