<?php

namespace Drupal\Tests\salesforce\salesforce_pull;

use Drupal\Core\Queue\QueueInterface;
use Drupal\salesforce_pull\QueueHandler;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce\SelectQueryResult;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce\Exception;
/**
 * Handles pull cron queue set up.
 *
 * @see \Drupal\salesforce_pull\QueueHandler
 */

class TestQueueHandler extends QueueHandler {
  /**
   * Override parent::create()
   * Chainable instantiation method for class
   *
   * @param object
   *  RestClient object
   */
  public static function create(RestClient $sfapi, array $mappings, QueueInterface $queue) {
    // Have to make sure we call this class instead of the original
    return new TestQueueHandler($sfapi, $mappings, $queue);
  }

  /**
   * Overrides parent::stateGet()
   * Wrapper for Drupal::state()->get()
   */
  protected function stateGet($name, $value) {
    return $value;
  }

  /**
   * Overrides parent::stateSet()
   * Wrapper for Drupal::state()->set()
   */
  protected function stateSet($name, $value) {
    return $value;
  }

  /**
   * Overrides parent::parseUrl()
   * Wrapper for Drupal::state()->set()
   */
  protected function parseUrl() {
    return 'https://example.salesforce.com';
  }

  /**
   * Overrides parent:: requestTime()
   * Wrapper for \Drupal::request()
   */
  protected function requestTime() {
    return $_SERVER['REQUEST_TIME'];
  }

  /**
   * Overrides parent:: watchdogException
   * Wrapper for watchdog_exception()
   */
  protected function watchdogException(\Exception $e) {
  }
}
