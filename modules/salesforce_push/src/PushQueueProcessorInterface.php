<?php

namespace Drupal\salesforce_push;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Interface implemented by Salesforce Push Queue Processors.
 *
 * Push Queue Processors are responsible for asynchronously processing a set of
 * push queue items (during cron).
 *
 * @ingroup plugin_api
 */
interface PushQueueProcessorInterface extends ContainerFactoryPluginInterface {

  /**
   * Process an array of push queue items.
   *
   * When an item is successfully processed, delete the item from queue via
   * PushQueue::deleteItem().
   *
   * @param array $items
   *   The items to process.
   *
   * @throws \Drupal\Core\Queue\SuspendQueueException
   *   Indicate that processing for this queue should not continue.
   *   Move on to the next queue.
   *   Items should be released.
   *
   * @throws \Drupal\Core\Queue\RequeueException
   *   Indicate that processing for this set of items failed.
   *   Processing for this queue should continue.
   *   Items should be released.
   *
   * @throws \Exception
   *   Indicate any other condition. Processing for this queue should continue.
   *   Items should not be released.
   */
  public function process(array $items);

}
