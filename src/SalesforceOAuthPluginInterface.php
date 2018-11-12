<?php

namespace Drupal\salesforce;

interface SalesforceOAuthPluginInterface extends SalesforceAuthProviderPluginInterface {

  public static function getAuthCallbackUrl();

  public function finalizeOauth();

  public function getConsumerSecret();

}