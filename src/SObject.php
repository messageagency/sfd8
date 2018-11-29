<?php

namespace Drupal\salesforce;

/**
 * Class SObject.
 *
 * @package Drupal\salesforce
 */
class SObject {
  protected $type;
  protected $fields;
  protected $id;

  /**
   * SObject constructor.
   *
   * @param array $data
   *   The SObject field data.
   *
   * @throws \Exception
   */
  public function __construct(array $data = []) {
    if (!isset($data['id']) && !isset($data['Id'])) {
      throw new \Exception('Refused to instantiate SObject without ID');
    }

    if (isset($data['id'])) {
      $data['Id'] = $data['id'];
    }
    $this->id = new SFID($data['Id']);
    unset($data['id'], $data['Id']);

    if (empty($data['attributes']) || !isset($data['attributes']['type'])) {
      throw new \Exception('Refused to instantiate SObject without Type');
    }
    $this->type = $data['attributes']['type'];

    // Attributes array also contains "url" index, which we don't need.
    unset($data['attributes']);
    $this->fields = [];
    foreach ($data as $key => $value) {
      $this->fields[$key] = $value;
    }
    $this->fields['Id'] = (string) $this->id;
  }

  /**
   * SFID Getter.
   *
   * @return \Drupal\salesforce\SFID
   *   The record id.
   */
  public function id() {
    return $this->id;
  }

  /**
   * Type getter.
   *
   * @return string
   *   The object type.
   */
  public function type() {
    return $this->type;
  }

  /**
   * Fields getter.
   *
   * @return array
   *   All SObject fields.
   */
  public function fields() {
    return $this->fields;
  }

  /**
   * Given $key, return corresponding field value.
   *
   * @return mixed
   *   The value.
   *
   * @throws \Exception
   *   If $key is not found.
   */
  public function field($key) {
    if (!array_key_exists($key, $this->fields)) {
      throw new \Exception('Index not found');
    }
    return $this->fields[$key];
  }

}
