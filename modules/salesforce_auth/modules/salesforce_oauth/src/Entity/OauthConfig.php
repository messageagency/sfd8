<?php

namespace Drupal\salesforce_oauth\Entity;

use Drupal\salesforce_auth\SalesforceAuthProviderInterface;
use Drupal\salesforce_auth\Entity\AuthConfigBase;

/**
 * Defines a Salesforce Mapping configuration entity class.
 *
 * @ConfigEntityType(
 *   id = "salesforce_oauth_config",
 *   label = @Translation("Salesforce OAuth Config"),
 *   module = "salesforce_oauth",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label"
 *   },
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\salesforce_oauth\Form\AuthConfigForm",
 *       "delete" = "Drupal\salesforce_auth\Form\AuthConfigDeleteForm",
 *       "revoke" = "Drupal\salesforce_oauth\Form\RevokeAuthorizationForm"
 *      },
 *     "list_builder" = "Drupal\salesforce_oauth\Entity\AuthConfigListBuilder"
 *   },
 *   links = {
 *     "collection" = "/admin/config/salesforce/authorize/oauth",
 *     "edit-form" = "/admin/config/salesforce/authorize/oauth/{salesforce_oauth_config}",
 *     "delete-form" = "/admin/config/salesforce/authorize/oauth/{salesforce_oauth_config}/delete",
 *     "revoke" = "/admin/config/salesforce/authorize/oauth/{salesforce_oauth_config}/revoke"
 *   },
 *   admin_permission = "authorize salesforce",
 *   config_export = {
 *     "id",
 *     "label",
 *     "consumer_key",
 *     "consumer_secret",
 *     "login_url",
 *   }
 * )
 */
class OAuthConfig extends AuthConfigBase {

  /**
   * Consumer secret.
   *
   * @var string
   */
  protected $consumer_secret;


  /**
   * Consumer key gtter.
   */
  public function getConsumerSecret() {
    return $this->consumer_secret;
  }

}