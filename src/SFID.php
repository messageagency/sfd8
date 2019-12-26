<?php

namespace Drupal\salesforce;

/**
 * Class SFID.
 *
 * @package Drupal\salesforce
 */
class SFID {

  /**
   * The id string value.
   *
   * @var string
   */
  protected $id;

  const MAX_LENGTH = 18;

  /**
   * SFID constructor.
   *
   * @param string $id
   *   The SFID.
   *
   * @throws \Exception
   */
  public function __construct($id) {
    if (strlen($id) != 15 && strlen($id) != self::MAX_LENGTH) {
      throw new \Exception('Invalid sfid ' . strlen($id));
    }
    $this->id = $id;
    if (strlen($this->id) == 15) {
      $this->id = static::convertId($id);
    }
  }

  /**
   * Given a potential SFID, return a new SFID object if it's valid.
   *
   * @param string $id
   *   A potential sfid.
   *
   * @return \Drupal\salesforce\SFID|false
   *   A new SFID if $id is valid, otherwise FALSE.
   */
  public static function createIfValid($id) {
    if (static::isValid($id)) {
      return new static($id);
    }
    return FALSE;
  }

  /**
   * Returns TRUE if $id is a valid length.
   *
   * @param string $id
   *   An sfid.
   *
   * @return bool
   *   Returns TRUE if $id is a valid length.
   */
  public static function isValid($id) {
    return strlen($id) == 15 || strlen($id) == self::MAX_LENGTH;
  }

  /**
   * Magic method wrapping the SFID string.
   *
   * @return string
   *   The SFID.
   */
  public function __toString() {
    return (string) $this->id;
  }

  /**
   * Convert 15-character Salesforce ID to an 18-character ID.
   *
   * Converts a 15-character case-sensitive Salesforce ID to 18-character
   * case-insensitive ID. If input is not 15-characters, return input unaltered.
   *
   * @param string $sfid15
   *   15-character case-sensitive Salesforce ID.
   *
   * @return string
   *   18-character case-insensitive Salesforce ID
   */
  private static function convertId($sfid15) {
    $chunks = str_split($sfid15, 5);
    $extra = '';
    foreach ($chunks as $chunk) {
      $chars = str_split($chunk, 1);
      $bits = '';
      foreach ($chars as $char) {
        $bits .= (!is_numeric($char) && $char == strtoupper($char)) ? '1' : '0';
      }
      $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ012345';
      $extra .= substr($map, base_convert(strrev($bits), 2, 10), 1);
    }
    return $sfid15 . $extra;
  }

}
