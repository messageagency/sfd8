<?php

namespace Drupal\salesforce_soap\Soap;

/**
 * A client for communicating with the Salesforce SOAP API.
 */
interface SoapClientInterface {

  /**
   * Establish a connection to the SOAP API.
   */
  public function connect();

  /**
   * Whether or not this client is connected to the SOAP API.
   */
  public function isConnected();

  /**
   * Salesforce SOAP API resource wrapper.
   *
   * Ensures the connection is established with the SOAP API prior to making the
   * call and automatically attempts a re-auth when the API responds with
   * invalid session ID / access token.
   *
   * @param string $function
   *   The name of the SOAP API function to attempt.
   * @param array $params
   *   (Optional) An array of parameters to pass through to the function.
   * @param bool $refresh
   *   (Optional) Refresh the access token prior to making the call.  Defaults
   *   to FALSE, in which case a refresh is only attempted if the API responds
   *   invalid session ID / access token.
   *
   * @return mixed
   *   The return value from $function.
   *
   * @see \SforcePartnerClient
   *
   * @throws \SoapFault
   * @throws \Exception
   */
  public function trySoap($function, array $params = [], $refresh = FALSE);

}
