<?php

namespace Drupal\salesforce_mapping\Plugin\Validation\Constraint;

/**
 * Checks if a set of entity fields has a unique value.
 *
 * @Constraint(
 *   id = "MappingEntity",
 *   label = @Translation("Mapping-SFID unique fields constraint", context = "Validation"),
 *   type = {"entity"}
 * )
 */
class MappingEntityConstraint extends UniqueFieldsConstraint {

  /**
   * {@inheritdoc}
   */
  public function __construct($options = NULL) {
    $options = [
      'fields' => [
        "drupal_entity.target_type",
        "drupal_entity.target_id",
        "salesforce_mapping",
      ],
    ];
    parent::__construct($options);
  }

}
