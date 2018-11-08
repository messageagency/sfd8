<?php

namespace Drupal\salesforce_mapping\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a salesforce mapping matches entity type of the given entity.
 */
class MappingEntityTypeConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    $drupal_entity = $entity->getMappedEntity() ?: $entity->getDrupalEntityStub();
    if (!$drupal_entity) {
      $this->context->addViolation('Validation failed. Please check your input and try again.');
      return;
    }
    if ($drupal_entity->getEntityTypeId() != $entity->getMapping()->getDrupalEntityType()) {
      $this->context->addViolation($constraint->message, [
        '%mapping' => $entity->getMapping()->label(),
        '%entity_type' => $drupal_entity->getEntityType()->getLabel(),
      ]);
    }
  }

}
