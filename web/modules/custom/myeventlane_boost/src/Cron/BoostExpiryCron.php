<?php

declare(strict_types=1);

namespace Drupal\myeventlane_boost\Cron;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\TimeInterface;
use Psr\Log\LoggerInterface;

final class BoostExpireCron {
  public function __construct(
    private EntityTypeManagerInterface $etm,
    private TimeInterface $time,
    private LoggerInterface $logger,
  ) {}

  public function __invoke(): void {
    $now = $this->time->getRequestTime();

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'event')
      ->condition('field_promoted', 1)
      ->exists('field_promo_expires')
      ->condition('field_promo_expires', $now, '<=')
      ->range(0, 500)
      ->execute();

    if (!$nids) { return; }

    $storage = $this->etm->getStorage('node');
    $nodes = $storage->loadMultiple($nids);

    foreach ($nodes as $node) {
      $node->set('field_promoted', 0);
      $node->set('field_promo_expires', NULL);
      $node->save();
    }

    $this->logger->notice('Unboosted @count expired event(s) via cron.', ['@count' => count($nodes)]);
  }
}
