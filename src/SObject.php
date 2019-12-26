<?php

namespace Drupal\salesforce;

/**
 * Class SObject.
 *
 * @package Drupal\salesforce
 */
class SObject {

  /**
   * The Salesforce table name, e.g. Contact, Account, etc.
   *
   * @var string
   */
  protected $type;

  /**
   * Key-value array of record fields.
   *
   * @var array
   */
  protected $fields;

  /**
   * The id.
   *
   * @var \Drupal\salesforce\SFID
   */
  protected $id;

  /**
   * SObject constructor.
   *
   * @param array $data
   *   The SObject field data.
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
   * Given SObject data, instantiate a new SObject if data is valid.
   *
   * @param array $data
   *   SOBject data.
   *
   * @return \Drupal\salesforce\SObject|false
   *   SObject, or FALSE if data is not valid.
   */
  public static function createIfValid(array $data = []) {
    if (!isset($data['id']) && !isset($data['Id'])) {
      return FALSE;
    }
    if (isset($data['id'])) {
      $data['Id'] = $data['id'];
    }
    if (!SFID::isValid($data['Id'])) {
      return FALSE;
    }
    if (empty($data['attributes']) || !isset($data['attributes']['type'])) {
      return FALSE;
    }
    return new static($data);
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
   * @return mixed|false
   *   The value.
   */
  public function hasField($key) {
    return array_key_exists($key, $this->fields);
  }

  /**
   * Given $key, return corresponding field value.
   *
   * @return mixed|null
   *   The value, or NULL if given $key is not set.
   *
   * @see hasField()
   */
  public function field($key) {
    if (!array_key_exists($key, $this->fields)) {
      return NULL;
    }
    return $this->fields[$key];
  }

}
