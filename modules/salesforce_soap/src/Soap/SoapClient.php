<?php

namespace Drupal\salesforce_soap\Soap;

use Drupal\salesforce\Rest\RestClientInterface;
use SforcePartnerClient;

/**
 * A client for communicating with the Salesforce SOAP API.
 */
class SoapClient extends SforcePartnerClient implements SoapClientInterface {

  /**
   * Indicates whether or not a successfull connection was made to the SOAP API.
   *
   * @var bool
   */
  protected $isConnected;

  /**
   * Salesforce REST API client.
   *
   * @var \Drupal\salesforce\Rest\RestClientInterface
   */
  protected $restApi;

  /**
   * Path to the WSDL that should be used.
   *
   * @var string
   */
  protected $wsdl;

  /**
   * Constructor which initializes the consumer.
   *
   * @param \Drupal\salesforce\Rest\RestClientInterface $rest_api
   *   The Salesforce REST API client.
   * @param string $wsdl
   *   (Optional) Path to the WSDL that should be used.  Defaults to using the
   *   partner WSDL from the developerforce/force.com-toolkit-for-php package.
   */
  public function __construct(RestClientInterface $rest_api, $wsdl = NULL) {
    parent::__construct();

    $this->restApi = $rest_api;

    if ($wsdl) {
      $this->wsdl = $wsdl;
    }
    else {
      // Determine location of the developerforce/force.com-toolkit-for-php WSDL
      // files.
      $reflector = new \ReflectionClass('SforcePartnerClient');
      $wsdl_dir = dirname($reflector->getFileName());
      // Use the partner WSDL.
      $this->wsdl = "$wsdl_dir/partner.wsdl.xml";
    }
  }

  /**
   * {@inheritdoc}
   */
  public function connect() {
    $this->isConnected = FALSE;
    // Use the "isAuthorized" callback to initialize session headers.
    if ($this->restApi->isAuthorized()) {
      $this->createConnection($this->wsdl);
      $token = $this->restApi->getAccessToken();
      if (!$token) {
        $token = $this->restApi->refreshToken();
      }
      $this->setSessionHeader($token);
      $this->setEndPoint($this->restApi->getApiEndPoint('partner'));
      $this->isConnected = TRUE;
    }
    else {
      throw new \Exception('Salesforce needs to be authorized to connect to this website.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isConnected() {
    return $this->isConnected;
  }

  /**
   * {@inheritdoc}
   */
  public function trySoap($function, array $params = [], $refresh = FALSE) {
    if ($refresh) {
      $this->restApi->refreshToken();
    }
    if (!$this->isConnected) {
      $this->connect();
    }
    try {
      $results = call_user_func_array([$this, $function], $params);
      return $results;
    }
    catch (SoapFault $e) {
      // sf:INVALID_SESSION_ID is thrown on expired login (and other reasons).
      // Our only recourse is to try refreshing our auth token. If we get any
      // other exception, bubble it up.
      if ($e->faultcode != 'sf:INVALID_SESSION_ID') {
        throw $e;
      }

      // If we didn't already try it, refresh the access token and try the call
      // again.
      if (!$refresh) {
        return $this->trySoap($function, $params, TRUE);
      }
      else {
        // Our connection is not working.
        $this->isConnected = FALSE;
        throw $e;
      }

    }
  }

}
