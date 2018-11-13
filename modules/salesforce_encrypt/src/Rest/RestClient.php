<?php

namespace Drupal\salesforce_encrypt\Rest;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\encrypt\EncryptServiceInterface;
use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\encrypt\EncryptionProfileManagerInterface;
use Drupal\salesforce\EntityNotFoundException;
use Drupal\salesforce\Rest\RestClient as SalesforceRestClient;
use Drupal\salesforce\SalesforceAuthProviderPluginManager;
use GuzzleHttp\ClientInterface;
use Drupal\Component\Datetime\TimeInterface;

/**
 * Objects, properties, and methods to communicate with the Salesforce REST API.
 *
 * @deprecated use \Drupal\salesforce\SalesforceAuthProviderPluginManager::getConfig() to access the current active auth configuration.
 */
class RestClient extends SalesforceRestClient implements EncryptedRestClientInterface {

}
