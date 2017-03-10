<?php

namespace Drupal\salesforce_pull;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Utility\Error;
use Drupal\salesforce\Event\SalesforceErrorEvent;
use Drupal\salesforce\Event\SalesforceNoticeEvent;
use Drupal\salesforce\Rest\RestClientInterface;
use Drupal\salesforce\SFID;
use Drupal\salesforce_mapping\MappingConstants;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

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
  protected $eventDispatcher;
  protected $request;

  private function __construct(RestClientInterface $sfapi, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, EventDispatcherInterface $event_dispatcher, Request $request) {
    $this->sfapi = $sfapi;
    $this->etm = $entity_type_manager;
    $this->mapping_storage = $this->etm->getStorage('salesforce_mapping');
    $this->mapped_object_storage = $this->etm->getStorage('salesforce_mapped_object');
    $this->state = $state;
    $this->eventDispatcher = $event_dispatcher;
    $this->request = $request;
  }

  /**
   * Chainable instantiation method for class.
   *
   * @param \Drupal\salesforce\Rest\RestClientInterface $sfapi
   *  RestClient object
   * @param \Drupal\Core\Entity\EntityTyprManagerInterface $$entity_type_manager
   *  Entity Manager service
   * @param \Drupal\Core\State\StatInterface $state
   *  State service
   */
  public static function create(RestClientInterface $sfapi, EntityTypeManagerInterface $entity_type_manager, StateInterface $state, EventDispatcherInterface $event_dispatcher, Request $request) {
    return new DeleteHandler($sfapi, $entity_type_manager, $state, $event_dispatcher, $request);
  }

  /**
   * Process deleted records from salesforce.
   */
  public function processDeletedRecords() {
    // @TODO Add back in SOAP, and use autoloading techniques
    foreach (array_reverse($this->mapping_storage->getMappedSobjectTypes()) as $type) {
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
    return true;
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
    $mapped_objects = $this->mapped_object_storage->loadBySfid(new SFID($record['id']));
    if (empty($mapped_objects)) {
      return;
    }

    foreach ($mapped_objects as $mapped_object) {
      $entity = $mapped_object->getMappedEntity();
      if (!$entity) {
        $message = 'No entity found for ID %id associated with Salesforce Object ID: %sfid ';
        $args = [
          '%id' => $mapped_object->entity_id->value,
          '%sfid' => $record['id'],
        ];
        $this->eventDispatcher->dispatch(new SalesforceNoticeEvent(NULL, $message, $args));
        $mapped_object->delete();
        return;
      }
    }

    // The mapping entity is an Entity reference field on mapped object, so we need to get the id value this way.
    $sf_mapping = $mapped_object->getMapping();
    if (!$sf_mapping) {
      $message = 'No mapping exists for mapped object %id with Salesforce Object ID: %sfid';
      $args = [
        '%id' => $mapped_object->id(),
        '%sfid' => $record['id'],
      ];
      $this->eventDispatcher->dispatch(new SalesforceNoticeEvent(NULL, $message, $args));
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
      $message = 'Deleted entity %label with ID: %id associated with Salesforce Object ID: %sfid';
      $args = [
        '%label' => $entity->label(),
        '%id' => $mapped_object->entity_id,
        '%sfid' => $record['id'],
      ];
      $this->eventDispatcher->dispatch(new SalesforceNoticeEvent(NULL, $message, $args));
    }
    catch (\Exception $e) {
      $message = '%type: @message in %function (line %line of %file).';
      $args = Error::decodeException($e);
      $this->eventDispatcher->dispatch(new SalesforceErrorEvent($e, $message, $args));
      // If mapped entity couldn't be deleted, do not delete the mapped object either.
      return;
    }

    $mapped_object->delete();
  }

}
