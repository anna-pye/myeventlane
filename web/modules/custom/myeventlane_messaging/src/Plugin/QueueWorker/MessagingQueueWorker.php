<?php

namespace Drupal\myeventlane_messaging\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\myeventlane_messaging\Service\MessagingManager;

/**
 * @QueueWorker(
 *   id = "myeventlane_messaging",
 *   title = @Translation("MyEventLane Messaging queue"),
 *   cron = {"time" = 60}
 * )
 */
final class MessagingQueueWorker extends QueueWorkerBase {
  public function processItem($data) : void {
    /** @var \Drupal\myeventlane_messaging\Service\MessagingManager $mgr */
    $mgr = \Drupal::service('myeventlane_messaging.manager');
    $mgr->sendNow($data);
  }
}
