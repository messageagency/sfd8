<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Entity\MappedObject.
 */

namespace Drupal\salesforce_mapping\Entity;

use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;
use Drupal\user\UserInterface;

/**
 * Defines a Salesforce Mapped Object entity class. Mapped Objects are content
 * entities, since they're defined by references to other content entities.
 *
 * @ContentEntityType(
 *   id = "salesforce_mapped_object",
 *   label = @Translation("Salesforce Mapped Object"),
 *   module = "salesforce_mapping",
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\salesforce_mapping\MappedObjectList",
 *     "form" = {
 *       "default" = "Drupal\salesforce_mapping\Form\MappedObjectForm",
 *       "add" = "Drupal\salesforce_mapping\Form\MappedObjectForm",
 *       "edit" = "Drupal\salesforce_mapping\Form\MappedObjectForm",
 *       "delete" = "Drupal\salesforce_mapping\Form\MappedObjectDeleteForm",
 *      },
 *     "access" = "Drupal\salesforce_mapping\MappedObjectAccessControlHandler",
 *   },
 *   base_table = "salesforce_mapped_object",
 *   revision_table = "salesforce_mapped_object_revision",
 *   admin_permission = "administer salesforce mapping",
 *   entity_keys = {
 *      "id" = "id",
 *      "entity_id" = "entity_id",
 *      "salesforce_id" = "salesforce_id",
 *      "revision" = "revision_id"
 *   }
 * )
 */
class MappedObject extends RevisionableContentEntityBase implements MappedObjectInterface {

  use EntityChangedTrait;

  /**
   * Overrides ContentEntityBase::__construct().
   */
  public function __construct(array $values) {
    // Drupal adds a layer of abstraction for translation purposes, even though we're talking about numeric identifiers that aren't language-dependent in any way, so we have to build our own constructor in order to allow callers to ignore this layer.
    foreach ($values as &$value) {
      if (!is_array($value)) {
        $value = [LanguageInterface::LANGCODE_DEFAULT => $value];
      }
    }
    parent::__construct($values, 'salesforce_mapped_object');
  }

  public function save() {
    if ($this->isNew()) {
      $this->created = REQUEST_TIME;
    }

    // Set the entity type and id fields appropriately.
    // @TODO do we still need this if we're not using entity ref field?
    $this->set('entity_id', $this->values['entity_id'][LanguageInterface::LANGCODE_DEFAULT]);
    $this->set('entity_type_id', $this->values['entity_type_id'][LanguageInterface::LANGCODE_DEFAULT]);
    return parent::save();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    // We can't use an entity reference, which requires a single entity type. We need to accommodate a reference to any entity type, as specified by entity_type_id
    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('Reference to the mapped Drupal entity.'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'hidden',
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ]);

    $fields['entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setDescription(t('The entity type to which this mapped object is attached.'))
      ->setRevisionable(TRUE)
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH)
      ->setDisplayOptions('form', [
        'type' => 'hidden',
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ]);

    $fields['salesforce_mapping'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Salesforce mapping'))
      ->setDescription(t('Salesforce mapping used to push/pull this mapped object'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'salesforce_mapping')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => -4,
      ])
      ->setSettings([
        'allowed_values' => [
          // SF Mappings for this entity type go here.
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ]);

    $fields['salesforce_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Salesforce ID'))
      ->setDescription(t('Reference to the mapped Salesforce object (SObject)'))
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', MappedObjectInterface::SFID_MAX_LENGTH)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the object mapping was created.'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => $i++,
      ]);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the object mapping was last edited.'))
      ->setRevisionable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ]);

    $fields['entity_updated'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Drupal Entity Updated'))
      ->setDescription(t('The Unix timestamp when the mapped Drupal entity was last updated.'))
      ->setRevisionable(TRUE)
      ->setDefaultValue(0)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ]);

    $fields['last_sync_status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status of most recent sync'))
      ->setDescription(t('Indicates whether most recent sync was successful or not.'))
      ->setRevisionable(TRUE);

    $fields['last_sync_action'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Action of most recent sync'))
      ->setDescription(t('Indicates acion which triggered most recent sync for this mapped object'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', SALESFORCE_MAPPING_TRIGGER_MAX_LENGTH)
      ->setRevisionable(TRUE);

    // @see ContentEntityBase::baseFieldDefinitions 
    // and RevisionLogEntityTrait::revisionLogBaseFieldDefinitions
    $fields += parent::baseFieldDefinitions($entity_type);

    return $fields;
  }

  public function getMappedEntity() {
    $entity_id = $this->entity_id->value;
    $entity_type_id = $this->entity_type_id->value;
    return $this->entityManager()->getStorage($entity_type_id)->load($entity_id);
  }

  public function getSalesforceLink($options = []) {
    $defaults = ['attributes' => ['target' => '_blank']];
    $options = array_merge($defaults, $options);
    return l($this->sfid(), $this->getSalesforceUrl(), $options);
  }

  public function getSalesforceUrl() {
    // @TODO dependency injection here:
    $sfapi = salesforce_get_api();
    if (!$sfapi) {
      return $this->salesforce_id->value;
    }
    return $sfapi->getInstanceUrl() . '/' . $this->salesforce_id->value;
  }

  public function sfid() {
    // this should be working, but doesn't
    return $this->get('salesforce_id')->value;
  }

  public function push() {
    // @TODO need error handling, logging, and hook invocations within this function, where we can provide full context, or short of that clear documentation on how callers should handle errors and exceptions. At the very least, we need to make sure to include $params in some kind of exception if we're not going to handle it inside this function.
    // @TODO better way to handle push/pull:
    $client = \Drupal::service('salesforce.client');

    $mapping = $this->salesforce_mapping->entity;

    // @TODO This is deprecated, but docs contain no pointer to the non-deprecated way to do it.

    $drupal_entity = \Drupal::entityTypeManager()
      ->getStorage($this->entity_type_id->value)
      ->load($this->entity_id->value);

    $params = $mapping->getPushParams($drupal_entity);

    // @TODO is this the right place for this logic to live?
    // Cases:
    // 1. upsert key is defined: use upsert
    // 2. no upsert key, no sfid: use create
    // 3. no upsert key, sfid: use update
    $result = FALSE;
    $action = '';
    if ($mapping->hasKey()) {
      $action = 'upsert';
      $result = $client->objectUpsert(
        $mapping->getSalesforceObjectType(),
        $mapping->getKeyField(),
        $mapping->getKeyValue($drupal_entity),
        $params
      );
    }
    elseif ($this->sfid()) {
      $action = 'update';
      $result = $client->objectUpdate(
        $mapping->getSalesforceObjectType(),
        $this->sfid(),
        $params
      );
    }
    else {
      $action = 'create';
      $result = $client->objectCreate(
        $mapping->getSalesforceObjectType(),
        $params
      );
    }
    // @TODO make $result a class with reliable properties, methods.

    if ($drupal_entity instanceof EntityChangedInterface) {
      // @TODO: where to get entity updated timestamp?
      $this->set('entity_updated', $drupal_entity->getChangedTime());
    }
    // dpm($result);

    // @TODO restore last_sync_action, last_sync_status, last_sync_message
    // @TODO: catch EntityStorageException ? Others ?
    $this
      ->set('salesforce_id', $result['id'])
      ->set('last_sync_action', 'push_' . $action)
      ->set('last_sync_status', TRUE)
      // ->set('last_sync_message', '')
      ->save();

    return $result;
  }

  public function pushDelete() {
    $client = \Drupal::service('salesforce.client');
    $mapping = $this->salesforce_mapping->entity;
    $result = $client->objectDelete($mapping->getSalesforceObjectType(), $this->sfid());
    $this
      ->set('last_sync_action', 'push_delete')
      ->set('last_sync_status', TRUE)
      ->save();

    return $result;
  }

  public function pull(array $sf_object = NULL, EntityInterface $drupal_entity = NULL) {
    $mapping = $this->salesforce_mapping->entity;

    if ($drupal_entity == NULL) {
      $drupal_entity = \Drupal::entityTypeManager()
        ->getStorage($this->entity_type_id->value)
        ->load($this->entity_id->value);
    }

    // If the pull isn't coming from a cron job
    if ($sf_object == NULL) {
      $sf_object = [];
      $client = \Drupal::service('salesforce.client');
      if ($this->sfid()) {
        $sf_object = $client->objectRead(
          $mapping->getSalesforceObjectType(),
          $this->sfid()
        );
      }
      elseif ($mapping->hasKey()) {
        $sf_object = $client->objectReadbyExternalId(
          $mapping->getSalesforceObjectType(),
          $mapping->getKeyField(),
          $mapping->getKeyValue($drupal_entity)
        );
        if (!empty($sf_object['Id'])) {
          $this->set('salesforce_id', $sf_object['Id']);
        }
      }
    }

    // No object found means there's nothing to pull.
    if (empty($sf_object)) {
      drupal_set_message('Nothing to pull. Please specify a Salesforce ID, or choose a mapping with an Upsert Key defined.', 'warning');
      return;
    }

    // @TODO better way to handle push/pull:
    $fields = $mapping->getPullFields($drupal_entity);

    foreach ($fields as $field) {
      // @TODO: The field plugin should be in charge of setting its value on an entity, we should not assume the field plugin's logic as we're doing here.
      $value = $sf_object[$field->get('salesforce_field')];
      $drupal_field = $field->get('drupal_field_value');
      if (isset($value)) {
        try {
          $drupal_entity->set($drupal_field, $value);
        }
        catch (\Exception $e) {
          $message = t('Exception during pull for @sfobj.@sffield @sfid to @dobj.@dprop @did with value @v: @e', [
            '@sfobj' => $mapping->getSalesforceObjectType(),
            '@sffield' =>  $sf_field,
            '@sfid' => $this->sfid(),
            '@dobj' => $this->entity_type_id->value,
            '@dprop' => $drupal_field,
            '@did' => $this->entity_id->value,
            '@v' => $value,
            '@e' => $e->getMessage()]);
          throw new \Exception($message, $e->getCode(), $e);
        }
      }
    }
    $drupal_entity->save();
    return $this;
  }

}
