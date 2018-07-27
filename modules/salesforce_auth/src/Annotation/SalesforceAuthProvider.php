<?php

namespace Drupal\salesforce_auth\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a SF auth provider annotation.
 *
 * @Annotation
 */
class SalesforceAuthProvider extends Plugin {

  /**
   * The plugin ID of the auth provider.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the auth provider.
   *
   * @ingroup plugin_translatable
   *
   * @var \Drupal\Core\Annotation\Translation
   */
  public $label;

}