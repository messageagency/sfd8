<?php

namespace Drupal\salesforce_push;

use Drupal\Core\Queue\ReliableQueueInterface;

/**
 * Push queue interface.
 */
interface PushQueueInterface extends ReliableQueueInterface {

  /**
   * Claim up to $n items from the current queue.
   *
   * If queue is empty, return an empty array.
   *
   * @param int $n
   *   Number of items to claim.
   * @param int $fail_limit
   *   Do not claim items with this many or more failures.
   * @param int $lease_time
   *   Time, in seconds, for which to hold this claim.
   *
   * @see DatabaseQueue::claimItem
   *
   * @return array
   *   Zero to $n Items indexed by item_id
   */
  public function claimItems($n, $fail_limit = 0, $lease_time = 0);

  /**
   * Inherited classes MUST throw an exception when this method is called.
   *
   * Use claimItems() instead.
   *
   * @param int $lease_time
   *   How long should the item remain claimed until considered released?
   *
   * @throws \Exception
   *   Whenever called.
   */
  public function claimItem($lease_time = NULL);

  /**
   * Failed item handler.
   *
   * Exception handler so that Queue Processors don't have to worry about what
   * happens when a queue item fails.
   *
   * @param \Throwable $e
   *   The exception which caused the failure.
   * @param object $item
   *   The failed item.
   */
  public function failItem(\Throwable $e, \stdClass $item);

}
