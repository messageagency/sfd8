<?php

namespace Drupal\salesforce;

interface SalesforceOAuthPluginInterface extends SalesforceAuthProviderPluginInterface {

  public function finalizeOauth();

  public function getConsumerSecret();

}