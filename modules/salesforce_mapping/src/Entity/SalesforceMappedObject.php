<?php

/**
 * @file
 * Contains \Drupal\salesforce_mapping\Entity\SalesforceMappedObject.
 */

namespace Drupal\salesforce_mapping\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\salesforce_mapping\Entity\SalesforceMappedObjectInterface;
use Drupal\Core\Entity\EntityChangedTrait;

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
 *       "delete" = "Drupal\salesforce_mapping\Form\SalesforceMappedObjectForm",
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

    // The caller should decide whether the entity updated or synched
    // if (!$this->entity_updated) {
    //   $this->entity_updated = REQUEST_TIME;
    // }
    // if (!$this->last_sync) {
    //   $this->last_sync = REQUEST_TIME;
    // }
    return parent::save();
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    // @todo Do we really have to define this, and hook_schema, and entity_keys?
    // so much redundancy.
    $fields = array();
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Salesforce Mapping Object ID'))
      ->setDescription(t('Primary Key: Unique salesforce_mapped_object entity ID.'))
      ->setReadOnly(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['entity_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Entity ID'))
      ->setDescription(t('Reference to the mapped Drupal entity.'))
      ->setRequired(TRUE)
      ->setDisplayOptions('form', array(
        'type' => 'hidden',
      ));

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setDescription(t('The entity type to which this comment is attached.'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', EntityTypeInterface::ID_MAX_LENGTH)
      ->setDisplayOptions('form', array(
        'type' => 'hidden',
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
      ));

    $fields['salesforce_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Salesforce object identifier'))
      ->setDescription(t('Reference to the mapped Salesforce object (SObject)'))
      ->setSetting('is_ascii', TRUE)
      ->setSetting('max_length', SalesforceMappedObjectInterface::SFID_MAX_LENGTH)
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => 0,
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
      ->setTranslatable(TRUE);

    $fields['entity_updated'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Drupal Entity Updated'))
      ->setDescription(t('The Unix timestamp when the mapped Drupal entity was last updated.'))
      ->setDefaultValue(0);
        
    $fields['last_sync'] =  BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last Sync'))
      ->setDescription(t('The Unix timestamp when the record was last synced with Salesforce.'))
      ->setDefaultValue(0);
    return $fields;
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
