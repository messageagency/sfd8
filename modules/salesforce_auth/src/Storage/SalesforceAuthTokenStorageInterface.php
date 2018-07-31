<?php

namespace Drupal\salesforce_auth\Storage;

use OAuth\Common\Storage\TokenStorageInterface;

/**
 * Interface SalesforceAuthTokenStorageInterface adds identity handling to token storage.
 *
 * @package Drupal\salesforce_auth\Storage
 */
interface SalesforceAuthTokenStorageInterface extends TokenStorageInterface {

  /**
   * Setter for identity storage.
   */
  public function storeIdentity($service, $identity);

  /**
   * Return boolean indicating whether this service has an identity.
   */
  public function hasIdentity($service);

  /**
   * Getter for identity.
   */
  public function retrieveIdentity($service);

  /**
   * Clear identity for service.
   */
  public function clearIdentity($service);

}