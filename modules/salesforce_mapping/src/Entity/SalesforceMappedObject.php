<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Entity\SalesforceMappedObject.
 */

namespace Drupal\salesforce_mapping\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappedObjectInterface;

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
 *     "list_builder" = "Drupal\salesforce_mapping\SalesforceMappedObjectList",
 *     "form" = {
 *       "default" = "Drupal\salesforce_mapping\Form\SalesforceMappedObjectForm",
 *       "add" = "Drupal\salesforce_mapping\Form\SalesforceMappedObjectForm",
 *       "edit" = "Drupal\salesforce_mapping\Form\SalesforceMappedObjectForm",
 *       "delete" = "Drupal\salesforce_mapping\Form\SalesforceMappedObjectDeleteForm",
 *      },
 *     "access" = "Drupal\salesforce_mapping\SalesforceMappedObjectAccessControlHandler",
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
class SalesforceMappedObject extends ContentEntityBase implements SalesforceMappedObjectInterface {

  use EntityChangedTrait;
  
  /**
   * Overrides ContentEntityBase::__construct().
   */
  public function __construct(array $values) {
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
    // @todo Do we really have to define this, and hook_schema, and entity_keys?
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
      ->setDisplayOptions('form', array(
        'type' => 'options_select',
        'weight' => -4,
      ))
      ->setSettings(array(
        'allowed_values' => array(
          // SF Mappings for this entity type go here.
          'female' => 'female',
          'male' => 'male',
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
      ->setSetting('max_length', SalesforceMappedObjectInterface::SFID_MAX_LENGTH)
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
    // @todo dependency injection here:
    $sfapi = salesforce_get_api();
    if (!$sfapi) {
      return $this->salesforce_id->value;
    }
    return $sfapi->getInstanceUrl() . '/' . $this->salesforce_id->value;
  }

  public function sfid() {
    return $this->get('salesforce_id')->value;
  }
}
