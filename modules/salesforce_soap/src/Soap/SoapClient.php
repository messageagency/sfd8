<?php

namespace Drupal\salesforce_soap\Soap;

use Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface;
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
   * Path to the WSDL that should be used.
   *
   * @var string
   */
  protected $wsdl;

  /**
   * Auth manager.
   *
   * @var \Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface
   */
  protected $authMan;

  /**
   * Constructor which initializes the consumer.
   *
   * @param \Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface $authMan
   *   Auth manager.
   * @param string $wsdl
   *   (Optional) Path to the WSDL that should be used.  Defaults to using the
   *   partner WSDL from the developerforce/force.com-toolkit-for-php package.
   *
   * @throws \ReflectionException
   */
  public function __construct(SalesforceAuthProviderPluginManagerInterface $authMan, $wsdl = NULL) {
    parent::__construct();
    $this->authMan = $authMan;

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
    if (!$token = $this->authMan->getToken()) {
      throw new \Exception('Salesforce needs to be authorized to connect to this website.');
    }
    $this->createConnection($this->wsdl);
    $this->setSessionHeader($token->getAccessToken());
    $this->setEndpoint($this->authMan->getProvider()->getApiEndpoint('partner'));
    $this->isConnected = TRUE;
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
      $this->authMan->refreshToken();
    }
    if (!$this->isConnected) {
      $this->connect();
    }
    try {
      $results = call_user_func_array([$this, $function], $params);
      return $results;
    }
    catch (\SoapFault $e) {
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
