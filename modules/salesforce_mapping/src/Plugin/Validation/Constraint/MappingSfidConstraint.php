<?php

namespace Drupal\salesforce_mapping\Plugin\Validation\Constraint;

/**
 * Checks if a set of entity fields has a unique value.
 *
 * @Constraint(
 *   id = "MappingSfid",
 *   label = @Translation("Mapping-SFID unique fields constraint", context = "Validation"),
 *   type = {"entity"}
 * )
 */
class MappingSfidConstraint extends UniqueFieldsConstraint {

  /**
   * {@inheritdoc}
   */
  public function __construct($options = NULL) {
    $options = ['fields' => ['salesforce_id', 'salesforce_mapping']];
    parent::__construct($options);
  }

}
