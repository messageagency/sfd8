<?php

namespace Drupal\salesforce_push\Plugin\SalesforcePushQueueProcessor;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Queue\SuspendQueueException;
use Drupal\salesforce\EntityNotFoundException;
use Drupal\salesforce\Rest\RestClient;
use Drupal\salesforce_push\PushQueue;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Rest queue processor plugin.
 *
 * @Plugin(
 *   id = "rest",
 *   label = @Translation("REST Push Queue Processor")
 * )
 */
class Rest extends PluginBase implements ContainerFactoryPluginInterface {
  protected $queue;
  protected $client;
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, PushQueue $queue, RestClient $client) {
    $this->queue = $queue;
    $this->client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('queue.salesforce_push'),
      $container->get('salesforce.client')
    );
  }

  public function process(array $items) {
    if (!$this->client->isAuthorized()) {
      throw new SuspendQueueException('Salesforce client not authorized.');
    }
    foreach ($items as $item) {
      try {
        $this->processItem($item);
        $this->queue->deleteItem($item);
      }
      catch (\Exception $e) {
        $this->queue->failItem($item, $e);
      }
    }
  }

  protected function processItem(\stdClass $item) {
    $mapping = salesforce_mapping_load($item->name);

    $entity = \Drupal::entityTypeManager()
      ->getStorage($mapping->get('drupal_entity_type'))
      ->load($item->entity_id);
    if (!$entity) {
      throw new EntityNotFoundException();
    }

    salesforce_push_sync_rest($entity, $mapping, $item->op);
    \Drupal::logger('Salesforce Push')->notice('Entity %type %id for salesforce mapping %mapping pushed successfully.',
      [
        '%type' => $mapping->get('drupal_entity_type'),
        '%id' => $item->entity_id,
        '%mapping' => $mapping->id(),
      ]
    );
  }

}