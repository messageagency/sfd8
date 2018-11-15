<?php

namespace Drupal\salesforce;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

interface SalesforceAuthProviderPluginInterface extends PluginFormInterface, PluginInspectionInterface {

  /**
   * @return \Drupal\salesforce\SalesforceAuthProviderInterface
   */
  public function service();

  /**
   * Get the login URL set for this auth provider.
   *
   * @return string
   */
  public function getLoginUrl();

}