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

}
