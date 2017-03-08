<?php

namespace Drupal\salesforce_pull\Plugin\QueueWorker;

/**
 * A Salesforce record puller that pulls on CRON run.
 *
 * @TODO how to make cron time configurable to admin, or at least via settings?
 *
 * @QueueWorker(
 *   id = "cron_salesforce_pull",
 *   title = @Translation("Salesforce Pull"),
 *   cron = {"time" = 180}
 * )
 */
class CronPull extends PullBase {}
