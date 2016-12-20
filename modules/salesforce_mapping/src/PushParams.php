<?php

namespace Drupal\salesforce_mapping;

use Symfony\Component\EventDispatcher\Event;

/**
 * Wrapper for the array of values which will be pushed to Salesforce.
 * Usable by salesforce.client for push actions: create, upsert, update
 */
class PushParams {
  
  protected $params;

  public __construct(array $params) {
    $this->params = $params;
    return $this;
  }

  public getParams() {
    return $this->params;
  }

  /**
   * @throws Exception
   */
  public getParam($key) {
    if (!array_key_exists($key, $this->params)) {
      throw new Exception("Param key $key does not exist");
    }
    return $this->params[$key];
  }

  public setParams(array $params) {
    $this->params = $params;
    return $this;
  }

  public setParam($key, $value) {
    $this->params[$key] = $value;
    return $this;
  }

}