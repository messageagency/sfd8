<?php

namespace Drupal\salesforce_auth;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

interface SalesforceAuthProviderPluginInterface extends PluginFormInterface, PluginInspectionInterface {

  /**
   * @return \Drupal\salesforce_auth\SalesforceAuthProviderInterface
   */
  public function service();

  /**
   * Get the login URL set for this auth provider.
   *
   * @return string
   */
  public function getLoginUrl();

}