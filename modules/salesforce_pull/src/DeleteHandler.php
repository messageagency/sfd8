<?php

namespace Drupal\salesforce_pull;

use Drupal\salesforce\Exception;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce\SFID;
use Drupal\salesforce\SelectQuery;
use Drupal\salesforce_mapping\Entity\SalesforceMapping;
use Drupal\salesforce_mapping\MappedObjectStorage;
use Drupal\salesforce_mapping\MappingConstants;
use Drupal\salesforce_mapping\SalesforceMappingStorage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;

/**
 * Handles pull cron deletion of Drupal entities based onSF mapping settings.
 *
 * @see \Drupal\salesforce_pull\DeleteHandler
 */

class DeleteHandler {

  protected $sfapi;
  protected $mapping_storage;
  protected $mapped_object_storage;
  protected $etm;
  protected $state;
  protected $logger;

  private function __construct(RestClient $sfapi, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, LoggerInterface $logger) {
    $this->sfapi = $sfapi;
    $this->etm = $entity_type_manager;
    $this->mapping_storage = $this->etm->getStorage('salesforce_mapping');
    $this->mapped_object_storage = $this->etm->getStorage('salesforce_mapped_object');
    $this->state = $state;
    $this->logger = $logger;
  }

  /**
   * Chainable instantiation method for class
   *
   * @param \Drupal\salesforce\Rest\RestClient $sfapi
   *  RestClient object
   * @param \Drupal\Core\Entity\EntityTyprManagerInterface $$entity_type_manager
   *  Entity Manager service
   * @param \Drupal\Core\State\StatInterface $state
   *  State service
   * @param Psr\Log\LoggerInterface $logger
   *  Logging service
   */
  public static function create(RestClient $sfapi, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, LoggerInterface $logger) {
    return new DeleteHandler($sfapi, $entity_type_manager, $state, $logger);
  }

  /**
   * Process deleted records from salesforce.
   */
  public function processDeletedRecords() {
    // @TODO Add back in SOAP, and use autoloading techniques
    foreach (array_reverse($this->mapping_storage->getMappedSobjectTypes()) as $type) {
      $last_delete_sync = $this->state->get('salesforce_pull_delete_last_' . $type, REQUEST_TIME);
      $now = time();
      // getDeleted() restraint: startDate must be at least one minute
      // greater than endDate.
      $now = $now > $last_delete_sync + 60 ? $now : $now + 60;
      $last_delete_sync_sf = gmdate('Y-m-d\TH:i:s\Z', $last_delete_sync);
      $now_sf = gmdate('Y-m-d\TH:i:s\Z', $now);
      $deleted = $this->sfapi->getDeleted($type, $last_delete_sync_sf, $now_sf);
      $this->handleDeletedRecords($deleted, $type);
      $this->state->set('salesforce_pull_delete_last_' . $type, REQUEST_TIME);
    }
  }

  protected function handleDeletedRecords(array $deleted, $type) {
    if (empty($deleted['deletedRecords'])) {
      return;
    }

    $sf_mappings = $this->mapping_storage->loadByProperties(
      ['salesforce_object_type' => $type]
    );
    if (empty($sf_mappings)) {
      return;
    }

    foreach ($deleted['deletedRecords'] as $record) {
      $this->handleDeletedRecord($record, $type);
    }
  }

  protected function handleDeletedRecord($record, $type) {
    $mapped_objects = $this->mapped_object_storage->loadBySFID(new SFID($record['id']));
    if (empty($mapped_objects)) {
      return;
    }

    foreach ($mapped_objects as $mapped_object) {
        $entity = $this->etm
          ->getStorage($mapped_object->entity_type_id->value)
          ->load($mapped_object->entity_id->value);
        if (!$entity) {
          $this->logger->log(
            LogLevel::NOTICE,
            'No entity found for ID %id associated with Salesforce Object ID: %sfid ',
            [
              '%id' => $mapped_object->entity_id->value,
              '%sfid' => $record['id'],
            ]
          );
          $mapped_object->delete();
          continue;
        }
      }

      // The mapping entity is an Entity reference field on mapped object, so we need to get the id value this way.
      $sf_mapping = $this->mapping_storage
        ->load($mapped_object->salesforce_mapping->entity->id());
      if (!$sf_mapping) {
        $this->logger->log(
          LogLevel::NOTICE,
          'No mapping exists for mapped object %id with Salesforce Object ID: %sfid',
          [
            '%id' => $mapped_object->id(),
            '%sfid' => $record['id'],
          ]
        );
        // @TODO should we delete a mapped object whose parent mapping no longer exists? Feels like someone else's job.
        // $mapped_object->delete();
        continue;
      }

      if (!$sf_mapping->checkTriggers([MappingConstants::SALESFORCE_MAPPING_SYNC_SF_DELETE])) {
        continue;
      }

      try {
        $entity->delete();
        $this->logger->log(
          LogLevel::NOTICE,
          'Deleted entity %label with ID: %id associated with Salesforce Object ID: %sfid',
          [
            '%label' => $entity->label(),
            '%id' => $mapped_object->entity_id,
            '%sfid' => $record->id,
          ]
        );
      }
      catch (\Exception $e) {
        $this->watchdogException($e);
        // If mapped entity couldn't be deleted, do not delete the mapped object either.
        continue;
      }

      $mapped_object->delete();
    }
  }
}
