<?php

namespace Drupal\salesforce_encrypt\Rest;

use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\salesforce\Rest\RestClient as SalesforceRestClient;
use Zend\Diactoros\Exception\DeprecatedMethodException;

/**
 * Objects, properties, and methods to communicate with the Salesforce REST API.
 *
 * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
 */
class RestClient extends SalesforceRestClient implements EncryptedRestClientInterface {

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
   */
  public function enableEncryption(EncryptionProfileInterface $profile) {
    throw new DeprecatedMethodException(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated. See release notes.');
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
   */
  public function disableEncryption() {
    throw new DeprecatedMethodException(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated. See release notes.');
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
   */
  public function getEncryptionProfile() {
    throw new DeprecatedMethodException(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated. See release notes.');
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
   */
  public function hookEncryptionProfileDelete(EncryptionProfileInterface $profile) {
    // noop
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
   */
  public function encrypt($value) {
    throw new DeprecatedMethodException(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated. See release notes.');
  }

  /**
   * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
   */
  public function decrypt($value) {
    throw new DeprecatedMethodException(__CLASS__ . '::' . __FUNCTION__ . '() is deprecated. See release notes.');
  }

}
