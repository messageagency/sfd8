<?php

namespace Drupal\salesforce_mapping\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates that a set of fields are unique for the given entity type.
 */
class UniqueFieldsConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($entity, Constraint $constraint) {
    $entity_type = $entity->getEntityType();
    $id_key = $entity_type->getKey('id');

    $query = \Drupal::entityQuery($entity_type->id())
      // The id could be NULL, so we cast it to 0 in that case.
      ->condition($id_key, (int) $entity->id(), '<>')
      ->range(0, 1);

    foreach ($constraint->fields as $field) {
      $field_name = $field;
      if (strpos($field_name, '.')) {
        list($field_name, $property) = explode('.', $field_name, 2);
      }
      else {
        $property = $entity->{$field}->getFieldDefinition()->getMainPropertyName();
      }
      $value = $entity->{$field_name}->{$property};
      $query->condition($field, $value);
    }

    if ($id = $query->execute()) {
      $id = reset($id);
      $entity = \Drupal::entityTypeManager()
        ->getStorage($entity_type->id())
        ->load($id);
      $url = $entity->toUrl();
      $message_replacements = [
        '@entity_type' => $entity_type->getLowercaseLabel(),
        ':url' => $url->toString(),
        '@label' => $entity->label(),
      ];
      $this->context->addViolation($constraint->message, $message_replacements);
    }
  }

}
