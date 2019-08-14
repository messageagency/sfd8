<?php

namespace Drupal\salesforce\Tests;

use Drupal\Core\Form\FormStateInterface;
use Drupal\salesforce\SalesforceAuthProviderPluginBase;
use Drupal\salesforce\Storage\SalesforceAuthTokenStorageInterface;
use OAuth\Common\Http\Client\ClientInterface;
use OAuth\Common\Token\TokenInterface;

class TestSalesforceAuthProvider extends SalesforceAuthProviderPluginBase {

  /**
   * @inheritDoc
   */
  public function __construct() {
    // NOOP.
  }

  /**
   * @inheritDoc
   */
  public function getInstanceUrl() {
    return 'https://example.com';
  }

  /**
   * @inheritDoc
   */
  public function refreshAccessToken(TokenInterface $token) {
    return TRUE;
  }

  /**
   * @inheritDoc
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return [];
  }

}