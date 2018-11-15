<?php

namespace Drupal\salesforce\Consumer;

interface SalesforceCredentialsInterface {

  public function getConsumerKey();

  public function getLoginUrl();

}