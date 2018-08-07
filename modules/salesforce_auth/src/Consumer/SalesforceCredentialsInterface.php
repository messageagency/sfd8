<?php

namespace Drupal\salesforce_auth\Consumer;

interface SalesforceCredentialsInterface {

  public function getConsumerKey();

  public function getLoginUrl();

}