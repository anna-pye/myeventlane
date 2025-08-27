<?php

declare(strict_types=1);

namespace Drupal\myeventlane_boost;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Psr\Log\LoggerInterface;

final class BoostManager {
  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
  ) {}

  public function applyBoost(int $event_nid, int $days): void {
    $event = $this->etm->getStorage('node')->load($event_nid);
    if (!$event || $event->bundle() !== 'event') {
      return;
    }

    $now = new \DateTimeImmutable('@' . $this->time->getRequestTime()); // UTC epoch
    $current_value = $event->get('field_promo_expires')->value ?: null;

    $base = $current_value
      ? new \DateTimeImmutable($current_value, new \DateTimeZone('UTC'))
      : $now;

    if ($base < $now) {
      $base = $now;
    }

    $expires = $base->modify(sprintf('+%d days', max(1, $days)))->setTimezone(new \DateTimeZone('UTC'));

    $event->set('field_promoted', 1);
    $event->set('field_promo_expires', $expires->format('Y-m-d\TH:i:s')); // store as UTC
    $event->save();

    $this->logger->info('Applied/Extended Boost: event @nid +@days days (until @exp)', [
      '@nid' => $event_nid,
      '@days' => $days,
      '@exp' => $expires->format(DATE_ATOM),
    ]);
  }
}

