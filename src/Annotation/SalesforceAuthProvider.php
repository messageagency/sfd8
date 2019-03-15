<?php

namespace Drupal\salesforce\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * SalesforceAuthProvider annotation definition.
 */
class SalesforceAuthProvider extends Plugin {

  /**
   * The plugin ID of the auth provider.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the key provider.
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

  /**
   * The credentials class specific to this provider.
   *
   * @var string
   */
  public $credentials_class; // @codingStandardsIgnoreLine

}
