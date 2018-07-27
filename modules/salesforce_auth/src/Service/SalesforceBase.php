<?php

namespace Drupal\salesforce_auth\Service;

use OAuth\OAuth2\Service\Salesforce;
use OAuth\Common\Http\Uri\Uri;

abstract class SalesforceBase extends Salesforce {

  /**
   * @var \Drupal\salesforce_auth\Consumer\SalesforceCredentials
   */
  protected $credentials;

  protected $configuration;

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationEndpoint() {
    return new Uri($this->credentials->getLoginUrl() . '/services/oauth2/authorize');
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessTokenEndpoint() {
    return new Uri($this->credentials->getLoginUrl() . '/services/oauth2/token');
  }

  /**
   * {@inheritdoc}
   */
  public function service() {
    // @todo get the service name from config
    // get class name without backslashes
    $classname = get_class($this);

    return preg_replace('/^.*\\\\/', '', $classname);
  }

}
