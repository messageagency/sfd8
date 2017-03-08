<?php

namespace Drupal\salesforce_pull;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\Error;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\SFID;
use Drupal\salesforce_mapping\MappedObjectStorage;
use Drupal\salesforce_mapping\MappingConstants;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles pull cron deletion of Drupal entities based onSF mapping settings.
 *
 * @see \Drupal\salesforce_pull\DeleteHandler
 */
class DeleteHandler {

  /**
   * Rest client service.
   *
   * @var \Drupal\salesforce\Rest\RestClientInterface
   */
  protected $sfapi;

  /**
   * Salesforce mapping storage service.
   *
   * @var \Drupal\salesforce_mapping\SalesforceMappingStorage
   */
  protected $mappingStorage;

  /**
   * Mapped Object storage service.
   *
   * @var \Drupal\salesforce_mapping\MappedObjectStorage
   */
  protected $mappedObjectStorage;

  /**
   * Entity tpye manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $etm;

  /**
   * State service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Logging service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Request service.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructor.
   *
   * @param \Drupal\salesforce\Rest\RestClientInterface $sfapi
   *   RestClient object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   * @param Psr\Log\LoggerInterface $logger
   *   Logging service.
   */
  private function __construct(RestClientInterface $sfapi, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, LoggerInterface $logger, Request $request) {
    $this->sfapi = $sfapi;
    $this->etm = $entity_type_manager;
    $this->mappingStorage = $this->etm->getStorage('salesforce_mapping');
    $this->mappedObjectStorage = $this->etm->getStorage('salesforce_mapped_object');
    $this->state = $state;
    $this->logger = $logger;
    $this->request = $request;
  }

  /**
   * Chainable instantiation method for class.
   *
   * @param \Drupal\salesforce\Rest\RestClientInterface $sfapi
   *   RestClient object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   * @param Psr\Log\LoggerInterface $logger
   *   Logging service.
   */
  public static function create(RestClientInterface $sfapi, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, LoggerInterface $logger, Request $request) {
    return new DeleteHandler($sfapi, $entity_type_manager, $state, $logger, $request);
  }

  /**
   * Process deleted records from salesforce.
   *
   * @return bool
   *   TRUE.
   */
  public function processDeletedRecords() {
    // @TODO Add back in SOAP, and use autoloading techniques
    foreach (array_reverse($this->mappingStorage->getMappedSobjectTypes()) as $type) {
      $last_delete_sync = $this->state->get('salesforce_pull_last_delete_' . $type, strtotime('-29 days'));
      $now = time();
      // getDeleted() restraint: startDate must be at least one minute
      // greater than endDate.
      $now = $now > $last_delete_sync + 60 ? $now : $now + 60;
      $last_delete_sync_sf = gmdate('Y-m-d\TH:i:s\Z', $last_delete_sync);
      $now_sf = gmdate('Y-m-d\TH:i:s\Z', $now);
      $deleted = $this->sfapi->getDeleted($type, $last_delete_sync_sf, $now_sf);
      $this->handleDeletedRecords($deleted, $type);
      $this->state->set('salesforce_pull_last_delete_' . $type, $now);
    }
    return TRUE;
  }

  /**
   * Delete records.
   *
   * @param array $deleted
   *   Array of deleted records.
   * @param string $type
   *   Salesforce object type.
   */
  protected function handleDeletedRecords(array $deleted, $type) {
    if (empty($deleted['deletedRecords'])) {
      return;
    }

    $sf_mappings = $this->mappingStorage->loadByProperties(
      ['salesforce_object_type' => $type]
    );
    if (empty($sf_mappings)) {
      return;
    }

    foreach ($deleted['deletedRecords'] as $record) {
      $this->handleDeletedRecord($record, $type);
    }
  }

  /**
   * Delete single mapped object.
   *
   * @param array $record
   *   Record array.
   * @param string $type
   *   Salesforce object type.
   */
  protected function handleDeletedRecord(array $record, $type) {
    $mapped_objects = $this->mappedObjectStorage->loadBySfid(new SFID($record['id']));
    if (empty($mapped_objects)) {
      return;
    }

    foreach ($mapped_objects as $mapped_object) {
      $entity = $mapped_object->getMappedEntity();
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
        return;
      }
    }

    // The mapping entity is an Entity reference field on mapped object, so we
    // need to get the id value this way.
    $sf_mapping = $mapped_object->getMapping();
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
      return;
    }

    if (!$sf_mapping->checkTriggers([MappingConstants::SALESFORCE_MAPPING_SYNC_SF_DELETE])) {
      return;
    }

    try {
      // Flag this entity to avoid duplicate processing.
      $entity->salesforce_pull = TRUE;

      $entity->delete();
      $this->logger->log(
        LogLevel::NOTICE,
        'Deleted entity %label with ID: %id associated with Salesforce Object ID: %sfid',
        [
          '%label' => $entity->label(),
          '%id' => $mapped_object->entity_id,
          '%sfid' => $record['id'],
        ]
      );
    }
    catch (\Exception $e) {
      $this->logger->log(
        LogLevel::ERROR,
        '%type: @message in %function (line %line of %file).',
        Error::decodeException($e)
      );
      // If mapped entity couldn't be deleted, do not delete the mapped object
      // either.
      return;
    }

    $mapped_object->delete();
  }

}
