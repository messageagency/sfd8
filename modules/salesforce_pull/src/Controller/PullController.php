<?php

namespace Drupal\salesforce_pull\Controller;

use Drupal\Component\Datetime\Time;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\RequeueException;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\Core\State\StateInterface;
use Drupal\salesforce\Event\SalesforceEvents;
use Drupal\salesforce\Event\SalesforceNoticeEvent;
use Drupal\salesforce\SFID;
use Drupal\salesforce_mapping\Entity\SalesforceMappingInterface;
use Drupal\salesforce_pull\DeleteHandler;
use Drupal\salesforce_pull\QueueHandler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Push controller.
 */
class PullController extends ControllerBase {

  const DEFAULT_TIME_LIMIT = 30;

  /**
   * Pull queue handler service.
   *
   * @var \Drupal\salesforce_pull\QueueHandler
   */
  protected $queueHandler;

  /**
   * Pull delete handler service.
   *
   * @var \Drupal\salesforce_pull\DeleteHandler
   */
  protected $deleteHandler;

  /**
   * Mapping storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mappingStorage;

  /**
   * Queue factory service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueService;

  /**
   * Queue worker manager.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueWorkerManager;

  /**
   * Event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Time.
   *
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * Current Request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * PushController constructor.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(QueueHandler $queueHandler, DeleteHandler $deleteHandler, EntityTypeManagerInterface $etm, ConfigFactoryInterface $configFactory, StateInterface $stateService, QueueFactory $queueService, QueueWorkerManagerInterface $queueWorkerManager, EventDispatcherInterface $eventDispatcher, Time $time, RequestStack $requestStack) {
    $this->queueHandler = $queueHandler;
    $this->deleteHandler = $deleteHandler;
    $this->mappingStorage = $etm->getStorage('salesforce_mapping');
    $this->configFactory = $configFactory;
    $this->stateService = $stateService;
    $this->queueService = $queueService;
    $this->queueWorkerManager = $queueWorkerManager;
    $this->eventDispatcher = $eventDispatcher;
    $this->time = $time;
    $this->request = $requestStack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('salesforce_pull.queue_handler'),
      $container->get('salesforce_pull.delete_handler'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('queue'),
      $container->get('plugin.manager.queue_worker'),
      $container->get('event_dispatcher'),
      $container->get('datetime.time'),
      $container->get('request_stack')
    );
  }

  /**
   * Page callback to process push queue for a given mapping.
   */
  public function endpoint(SalesforceMappingInterface $salesforce_mapping = NULL, $key = NULL, $id = NULL) {
    // If standalone for this mapping is disabled, and global standalone is
    // disabled, then "Access Denied" for this mapping.
    if ($key != $this->stateService->get('system.cron_key')) {
      throw new AccessDeniedHttpException();
    }
    $global_standalone = $this->config('salesforce.settings')->get('standalone');
    if (!$salesforce_mapping && !$global_standalone) {
      throw new AccessDeniedHttpException();
    }
    if ($salesforce_mapping && !$salesforce_mapping->doesPullStandalone() && !$global_standalone) {
      throw new AccessDeniedHttpException();
    }
    if ($id) {
      try {
        $id = new SFID($id);
      }
      catch (\Exception $e) {
        throw new AccessDeniedHttpException();
      }
    }
    $this->populateQueue($salesforce_mapping, $id);
    $this->processQueue();
    if ($this->request->get('destination')) {
      return new RedirectResponse($this->request->get('destination'));
    }
    return new Response('', 204);
  }

  /**
   * Helper method to populate queue, optionally by mapping or a single record.
   */
  protected function populateQueue(SalesforceMappingInterface $mapping = NULL, SFID $id = NULL) {
    $mappings = [];
    if ($id) {
      return $this->queueHandler->getSingleUpdatedRecord($mapping, $id, TRUE);
    }

    if ($mapping != NULL) {
      $mappings[] = $mapping;
    }
    else {
      $mappings = $this->mappingStorage->loadByProperties([["pull_standalone" => TRUE]]);
    }

    foreach ($mappings as $mapping) {
      $this->queueHandler->getUpdatedRecordsForMapping($mapping);
    }
  }

  /**
   * Helper method to get queue processing time limit.
   */
  protected function getTimeLimit() {
    return self::DEFAULT_TIME_LIMIT;
  }

  /**
   * Helper method to process queue.
   */
  protected function processQueue() {
    $start = microtime(TRUE);
    $worker = $this->queueWorkerManager->createInstance(QueueHandler::PULL_QUEUE_NAME);
    $end = time() + $this->getTimeLimit();
    $queue = $this->queueService->get(QueueHandler::PULL_QUEUE_NAME);
    $count = 0;
    while ((!$this->getTimeLimit() || time() < $end) && ($item = $queue->claimItem())) {
      try {
        $this->eventDispatcher->dispatch(SalesforceEvents::NOTICE, new SalesforceNoticeEvent(NULL, 'Processing item @id from @name queue.', ['@name' => QueueHandler::PULL_QUEUE_NAME, '@id' => $item->item_id]));
        $worker->processItem($item->data);
        $queue->deleteItem($item);
        $count++;
      }
      catch (RequeueException $e) {
        // The worker requested the task to be immediately requeued.
        $queue->releaseItem($item);
      }
      catch (SuspendQueueException $e) {
        // If the worker indicates there is a problem with the whole queue,
        // release the item.
        $queue->releaseItem($item);
        throw new \Exception($e->getMessage());
      }
    }
    $elapsed = microtime(TRUE) - $start;
    $this->eventDispatcher->dispatch(SalesforceEvents::NOTICE, new SalesforceNoticeEvent(NULL, 'Processed @count items from the @name queue in @elapsed sec.', [
      '@count' => $count,
      '@name' => QueueHandler::PULL_QUEUE_NAME,
      '@elapsed' => round($elapsed, 2),
    ]));
  }

}
