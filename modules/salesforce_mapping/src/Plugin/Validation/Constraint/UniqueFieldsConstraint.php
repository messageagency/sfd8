<?php

namespace Drupal\salesforce_mapping\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Checks if a set of entity fields has a unique value.
 *
 * @Constraint(
 *   id = "UniqueFields",
 *   label = @Translation("Unique fields constraint", context = "Validation"),
 *   type = {"entity"}
 * )
 */
class UniqueFieldsConstraint extends Constraint {

  /**
   * Constraint message.
   *
   * @var string
   */
  public $message = 'A @entity_type already exists: <a href=":url">@label</a>';

  /**
   * Array of unique fields.
   *
   * @var array
   */
  public $fields;

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions() {
    return ['fields'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption() {
    return 'fields';
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy() {
    return '\Drupal\salesforce_mapping\Plugin\Validation\Constraint\UniqueFieldsConstraintValidator';
  }

}
