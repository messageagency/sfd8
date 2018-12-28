<?php

namespace Drupal\salesforce;

/**
 * OAuth user-agent plugin interface.
 *
 * OAuth user-agent flow requires a 2-part handshake to complete authentication.
 * This interface exposes methods to make the handshake possible.
 */
interface SalesforceOAuthPluginInterface extends SalesforceAuthProviderPluginInterface {

  /**
   * Complete the OAuth user-agent handshake.
   *
   * @return bool
   *   TRUE if oauth finalization was successful.
   *
   * @throws \OAuth\Common\Http\Exception\TokenResponseException
   *
   * @see \Drupal\salesforce\Controller\SalesforceOAuthController
   */
  public function finalizeOauth();

  /**
   * Getter for consumer secret.
   *
   * @return string
   *   The consumer secret.
   */
  public function getConsumerSecret();

}
