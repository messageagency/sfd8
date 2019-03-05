<?php

namespace Drupal\salesforce_push;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Push controller.
 */
class PushController extends ControllerBase {

  /**
   * Push queue service.
   *
   * @var \Drupal\salesforce_push\PushQueue
   */
  protected $pushQueue;

  /**
   * Mapping storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $mappingStorage;

  /**
   * PushController constructor.
   *
   * @param \Drupal\salesforce_push\PushQueue $pushQueue
   *   Push queue service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   Entity type manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(PushQueue $pushQueue, EntityTypeManagerInterface $etm) {
    $this->pushQueue = $pushQueue;
    $this->mappingStorage = $etm->getStorage('salesforce_mapping');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('queue.salesforce_push'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Page callback to process the entire push queue.
   */
  public function endpoint() {
    // "Access Denied" if standalone global config not enabled.
    if (!$this->config('salesforce.settings')->get('standalone')) {
      throw new AccessDeniedHttpException();
    }
    $this->pushQueue->processQueues();
    return new Response('', 204);
  }

  /**
   * Page callback to process push queue for a given mapping.
   */
  public function mappingEndpoint($salesforce_mapping) {
    $mapping = $this->mappingStorage->load($salesforce_mapping);
    // If standalone for this mapping is disabled, and global standalone is
    // disabled, then "Access Denied" for this mapping.
    if (!$mapping->doesPushStandalone()
    && !\Drupal::config('salesforce.settings')->get('standalone')) {
      throw new AccessDeniedHttpException();
    }
    $this->pushQueue->processQueue($mapping);
    return new Response('', 204);
  }

}
