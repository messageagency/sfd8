<?php

namespace Drupal\salesforce_push;

use Drupal\Core\Queue\ReliableQueueInterface;

interface PushQueueInterface extends ReliableQueueInterface {

  /**
   * Claim up to $n items from the current queue.
   *
   * If queue is empty, return an empty array.
   *
   * @see DatabaseQueue::claimItem
   *
   * @param int $n
   *   Number of items to claim.
   * @param int $fail_limit
   *   Do not claim items with this many or more failures.
   * @param int $lease_time
   *   Time, in seconds, for which to hold this claim.
   *
   * @return array
   *   Zero to $n Items indexed by item_id
   */
  public function claimItems($n, $fail_limit = 0, $lease_time = 0);

  /**
   * Inherited classes must throw an exception when this method is called.
   * Use claimItems() instead.
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
   * @param Exception $e
   * @param stdClass $item
   */
  public function failItem(\Exception $e, \stdClass $item);


}

