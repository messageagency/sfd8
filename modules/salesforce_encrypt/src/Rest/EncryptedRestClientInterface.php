<?php

namespace Drupal\salesforce_encrypt\Rest;

use Drupal\encrypt\EncryptionProfileInterface;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce_encrypt\SalesforceEncryptedAuthTokenStorageInterface;

/**
 * Objects, properties, and methods to communicate with the Salesforce REST API.
 *
 * @deprecated use SalesforceEncryptedAuthTokenStorage
 */
interface EncryptedRestClientInterface extends RestClientInterface {

}
