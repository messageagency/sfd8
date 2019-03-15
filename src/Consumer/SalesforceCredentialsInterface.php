<?php

namespace Drupal\salesforce\Consumer;

/**
 * Salesforce credentials interface.
 */
interface SalesforceCredentialsInterface {

  /**
   * Get the consumer key for these credentials.
   *
   * @return string
   *   The consumer key.
   */
  public function getConsumerKey();

  /**
   * Get the login URL for these credentials.
   *
   * @return string
   *   The login url, e.g. https://login.salesforce.com.
   */
  public function getLoginUrl();

  /**
   * Sanity check for credentials validity.
   *
   * @return bool
   *   TRUE if credentials are set properly. Otherwise false.
   */
  public function isValid();

  /**
   * Create helper.
   *
   * @param array $configuration
   *   Plugin configuration.
   *
   * @return static
   */
  public static function create(array $configuration);

}
