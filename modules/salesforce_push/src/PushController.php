<?php

namespace Drupal\salesforce_push;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Entity\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
  
class PushController extends ControllerBase {

  protected $pushQueue;
  protected $mappingStorage;

  public function __construct(PushQueue $pushQueue, EntityManagerInterface $entity_manager) {
    $this->pushQueue = $pushQueue;
    $this->mappingStorage = $entity_manager->getStorage('salesforce_mapping');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('queue.salesforce_push'),
      $container->get('entity.manager')
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