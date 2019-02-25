<?php

namespace Drupal\salesforce_mapping\Entity;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\salesforce\SObject;

/**
 * Mapped Object interface.
 */
interface MappedObjectInterface extends EntityChangedInterface, RevisionLogInterface, ContentEntityInterface {

  /**
   * Get the attached mapping entity.
   *
   * @return SalesforceMappingInterface
   *   The mapping entity.
   */
  public function getMapping();

  /**
   * Get the mapped Drupal entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The mapped Drupal entity.
   */
  public function getMappedEntity();

  /**
   * Return a numeric timestamp for comparing to Salesforce record timestamp.
   *
   * @return int
   *   The entity_updated value from the Mapped Object.
   */
  public function getChanged();

  /**
   * Wrapper for salesforce.client service.
   *
   * @return \Drupal\salesforce\Rest\RestClientInterface
   *   The service.
   */
  public function client();

  /**
   * Wrapper for Drupal core event_dispatcher service.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   Event dispatcher.
   */
  public function eventDispatcher();

  /**
   * Wrapper for config getter.
   */
  public function config($name);

  /**
   * Wrapper for salesforce auth provider plugin manager.
   *
   * @return \Drupal\salesforce\SalesforceAuthProviderPluginManagerInterface
   *   The auth provider plugin manager.
   */
  public function authMan();

  /**
   * Get a salesforce url for the linked record.
   *
   * @return string
   *   The salesforce url for the linked SF record.
   */
  public function getSalesforceUrl();

  /**
   * Attach a Drupal entity to the mapped object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to be attached.
   *
   * @return $this
   */
  public function setDrupalEntityStub(EntityInterface $entity = NULL);

  /**
   * Wrapper for drupalEntityStub.
   *
   * @return \Drupal\Core\Entity\FieldableEntityInterface
   *   The mapped entity.
   */
  public function getDrupalEntityStub();

  /**
   * Get the mapped Salesforce record, only available during pull.
   *
   * @return \Drupal\salesforce\SObject
   *   The SObject data, available only during pull.
   */
  public function getSalesforceRecord();

  /**
   * Getter for salesforce_id.
   *
   * @return string
   *   SFID.
   */
  public function sfid();

  /**
   * Push data to Salesforce.
   *
   * @return mixed
   *   SFID or NULL depending on result from SF.
   */
  public function push();

  /**
   * Delete the mapped SF object in Salesforce.
   *
   * @return $this
   */
  public function pushDelete();

  /**
   * Set a Drupal entity for this mapped object.
   *
   * @return $this
   */
  public function setDrupalEntity(EntityInterface $entity = NULL);

  /**
   * Assign Salesforce data to this mapped object, in preparation for saving.
   *
   * @param \Drupal\salesforce\SObject $sfObject
   *   The sobject.
   *
   * @return $this
   */
  public function setSalesforceRecord(SObject $sfObject);

  /**
   * Pull the mapped SF object data from Salesforce.
   *
   * @return $this
   */
  public function pull();

  /**
   * Based on the Mapped Object revision limit, delete old revisions.
   *
   * @return $this
   */
  public function pruneRevisions(EntityStorageInterface $storage);

}
