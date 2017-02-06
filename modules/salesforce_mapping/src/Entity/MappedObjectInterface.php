<?php

namespace Drupal\salesforce_mapping\Entity;

use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\salesforce\SObject;  
  
/**
 *
 */
interface MappedObjectInterface extends EntityChangedInterface, RevisionLogInterface {

  /**
   * @return return SalesforceMappingInterface
   */
  public function getMapping();

  /**
   * @return EntityInterface
   */
  public function getMappedEntity();

  /**
   * @return Link
   */
  public function getSalesforceLink(array $options = []);

  /**
   * Wrapper for salesforce.client Drupal\salesforce\Rest\RestClient service
   */
  public function client();

  /**
   * Wrapper for Drupal core event_dispatcher service.
   */
  public function eventDispatcher();

  /**
   * Wrapper for Drupal core logger service.
   * @param string $log the log name
   */
  public function logger($log);

  /**
   * @return string
   */
  public function getSalesforceUrl();

  /**
   * @return string
   *   SFID
   */
  public function sfid();

  /**
   * @return mixed
   *  SFID or NULL depending on result from SF.
   */
  public function push();

  /**
   * @return $this
   */
  public function pushDelete();

  public function setDrupalEntity(EntityInterface $entity = NULL);

  public function setSalesforceRecord(SObject $sf_object);

  /**
   * @return $this
   */
  public function pull();

}
