<?php

namespace Drupal\salesforce_pull\Plugin\QueueWorker;

/**
 * A Salesforce record puller that pulls on CRON run.
 *
 * @QueueWorker(
 *   id = "cron_salesforce_pull",
 *   title = @Translation("Salesforce Pull"),
 *   cron = {"time" = 180}
 * )
 */
class CronPull extends PullBase {}
