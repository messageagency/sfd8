<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Entity\MappedObject.
 */

namespace Drupal\salesforce_mapping\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\salesforce_mapping\Entity\MappedObjectInterface;

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
 *   admin_permission = "administer salesforce mapping",
 *   entity_keys = {
 *      "id" = "id",
 *      "entity_id" = "entity_id",
 *      "salesforce_id" = "salesforce_id"
 *   }
 * )
 */
class MappedObject extends ContentEntityBase implements MappedObjectInterface {

  use EntityChangedTrait;
  
  /**
   * Overrides ContentEntityBase::__construct().
   */
  public function __construct(array $values) {
    // Drupal adds a layer of abstraction for translation purposes, even though we're talking about numeric identifiers that aren't language-dependent in any way, so we have to build our own constructor in order to allow callers to ignore this layer.
    foreach ($values as &$value) {
      if (!is_array($value)) {
        $value = array(LanguageInterface::LANGCODE_DEFAULT => $value);
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
    // @TODO Do we really have to define this, and hook_schema, and entity_keys?
    // so much redundancy.
    $i = 0;
    $fields = array();
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Salesforce Mapping Object ID'))
      ->setDescription(t('Primary Key: Unique salesforce_mapped_object entity ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    // We can't use an entity reference, which requires a single entity type. We need to accommodate a reference to any entity type, as specified by entity_type_id
    $fields['entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('Reference to the mapped Drupal entity.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', array(
        'type' => 'hidden',
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ));

    $fields['entity_type_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setDescription(t('The entity type to which this comment is attached.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH)
      ->setDisplayOptions('form', array(
        'type' => 'hidden',
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ));

    $fields['salesforce_mapping'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Salesforce mapping'))
      ->setDescription(t('Salesforce mapping used to push/pull this mapped object'))
      ->setSetting('target_type', 'salesforce_mapping')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', array(
        'type' => 'options_select',
        'weight' => -4,
      ))
      ->setSettings(array(
        'allowed_values' => array(
          // SF Mappings for this entity type go here.
        ),
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ));

    $fields['salesforce_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Salesforce ID'))
      ->setDescription(t('Reference to the mapped Salesforce object (SObject)'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', MappedObjectInterface::SFID_MAX_LENGTH)
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => 0,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the object mapping was created.'))
      ->setReadOnly(TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'hidden',
        'type' => 'timestamp',
        'weight' => $i++,
      ));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the object mapping was last edited.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ));


    $fields['entity_updated'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Drupal Entity Updated'))
      ->setDescription(t('The Unix timestamp when the mapped Drupal entity was last updated.'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ));
        
    $fields['last_sync'] =  BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last Sync'))
      ->setDescription(t('The Unix timestamp when the record was last synced with Salesforce.'))
      ->setDefaultValue(0)
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => $i++,
      ));
    return $fields;
  }

  public function getMappedEntity() {
    $entity_id = $this->entity_id->value;
    $entity_type_id = $this->entity_type_id->value;
    return $this->entityManager()->getStorage($entity_type_id)->load($entity_id);
  }

  public function getSalesforceLink($options = array()) {
    $defaults = array('attributes' => array('target' => '_blank'));
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
    return $this->get('salesforce_id')->value;
  }

  public function push() {
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
    if ($mapping->hasKey()) {
      $client->objectUpsert(
        $mapping->salesforce_object_type,
        $mapping->getKeyField()->salesforce_field->Name,
        $mapping->getKeyValue($drupal_entity),
        $params
      );
      // Upsert doesn't return a useful response, so we have to craft our own.
      $result = $client->objectReadKey(
        $mapping->salesforce_object_type,
        $mapping->getKeyField()->salesforce_field->Name,
        $mapping->getKeyValue($drupal_entity)
      );
      $result->id = $result->Id;
    }
    elseif ($this->sfid()) {
      $result = $client->objectUpdate($mapping->salesforce_object_type, $this->sfid(), $params);
    }
    else {
      $result = $client->objectCreate($mapping->salesforce_object_type, $params);
    }
    // @TODO make this a class with reliable properties, methods.
    return $result;
  }
}
