<?php

namespace Drupal\salesforce\Rest;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SObject;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\SelectQueryResult;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\salesforce\Rest\RestClientBaseInterface;

/**
 * Objects, properties, and methods to communicate with the Salesforce REST API.
 */
interface RestClientInterface extends RestClientBaseInterface {

  /**
   * Constructor which initializes the consumer.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The GuzzleHttp Client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache service.
   * @param \Drupal\Component\Serialization\Json $json
   *   The JSON serializer service.
   */
  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, StateInterface $state, CacheBackendInterface $cache, Json $json);

}
