<?php

namespace Drupal\salesforce_auth\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines a Salesforce Auth entity.
 *
 * @ConfigEntityType(
 *   id = "salesforce_auth",
 *   label = @Translation("Salesforce Auth"),
 *   module = "salesforce_auth",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\salesforce_auth\Controller\SalesforceAuthListBuilder",
 *     "form" = {
 *       "default" = "Drupal\salesforce_auth\Form\SalesforceAuthForm",
 *       "delete" = "Drupal\salesforce_auth\Form\SalesforceAuthDeleteForm",
 *       "revoke" = "Drupal\salesforce_auth\Form\SalesforceAuthRevokeForm"
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
class SalesforceAuth extends ConfigEntityBase {

  /**
   * Auth id. e.g. "full_sandbox".
   *
   * @var string
   */
  protected $id;

  /**
   * Auth label. e.g. "Full Sandbox".
   *
   * @var string
   */
  protected $label;

  /**
   * @var \Drupal\salesforce_auth\Plugin\SalesforceAuthProviderPluginInterface
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
   * @return \Drupal\salesforce_auth\Plugin\SalesforceAuthProviderPluginInterface
   */
  public function getPlugin() {
    return $this->plugin;
  }

  public function getPluginId() {
    return $this->plugin ? $this->plugin->id() : NULL;
  }

  public function getAuthProvider() {

  }

  public function getAccessToken() {

  }

  public function refreshToken() {

  }

  public function getLoginUrl() {

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
    $manager = \Drupal::service("plugin.manager.salesforce_auth.providers");

    $options = [];
    foreach ($manager->getDefinitions() as $id => $definition) {
      $options[$id] = ($definition['label']);
    }

    return $options;
  }

}