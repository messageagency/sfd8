<?php

namespace Drupal\salesforce;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Auth provider plugin interface.
 */
interface SalesforceAuthProviderPluginInterface extends PluginFormInterface, PluginInspectionInterface {

  /**
   * The auth provider service.
   *
   * @return \Drupal\salesforce\SalesforceAuthProviderInterface
   *   The auth provider service.
   */
  public function service();

  /**
   * Login URL set for this auth provider.
   *
   * @return string
   *   Login URL set for this auth provider.
   */
  public function getLoginUrl();

}
