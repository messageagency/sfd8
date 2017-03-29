<?php

namespace Drupal\salesforce;

class SFID {
  
  protected $id;
  const MAX_LENGTH = 18;
  
  public function __construct($id) {
    if (strlen($id) != 15 && strlen($id) != self::MAX_LENGTH) {
      throw new \Exception('Invalid sfid ' . strlen($id));
    }
    $this->id = $id;
    if (strlen($this->id) == 15) {
      $this->id = self::convertId($id);
    }
  }

  public function __toString() {
    return $this->id;
  }

  /**
   * Converts a 15-character case-sensitive Salesforce ID to 18-character
   * case-insensitive ID. If input is not 15-characters, return input unaltered.
   *
   * @param string $sfid15
   *   15-character case-sensitive Salesforce ID
   * @return SFID
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
