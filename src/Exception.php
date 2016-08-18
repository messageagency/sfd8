<?php

/**
 * @file
 * Contains \Drupal\salesforce\Exception.
 */

namespace Drupal\salesforce;

use Symfony\Component\Serializer\Exception\Exception as SymfonyException;

class Exception extends \RuntimeException implements SymfonyException {

}
