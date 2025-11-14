<?php

declare(strict_types=1);

namespace Drupal\myeventlane_boost\Cron;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\TimeInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Mail\MailManagerInterface;

final class BoostExpiryReminderCron {
  public function __construct(
    private EntityTypeManagerInterface $etm,
    private TimeInterface $time,
    private LoggerInterface $logger,
    private MailManagerInterface $mail
  ) {}

  public function __invoke(): void {
    $now = $this->time->getRequestTime();
    $in24 = $now + 24 * 3600;

    $nids = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'event')
      ->condition('field_promoted', 1)
      ->exists('field_promo_expires')
      ->condition('field_promo_expires', $now, '>')
      ->condition('field_promo_expires', $in24, '<=')
      ->range(0, 200)
      ->execute();
    if (!$nids) { return; }

    $nodes = $this->etm->getStorage('node')->loadMultiple($nids);
    foreach ($nodes as $node) {
      $owner = $node->getOwner();
      $mail = $owner?->getEmail();
      if (!$mail) { continue; }

      $params = [
        'subject' => t('Your event boost expires soon'),
        'message' => t('Heads up! The boost for "@title" expires in ~24 hours. Extend here: @url', [
          '@title' => $node->label(),
          '@url' => \Drupal::url('myeventlane_boost.boost_page', ['node' => $node->id()], ['absolute' => TRUE]),
        ]),
      ];
      $this->mail->mail('myeventlane_boost', 'boost_expiring', $mail, $owner->getPreferredLangcode() ?? 'en', $params);
    }

    $this->logger->notice('Sent boost expiry reminders for @count events.', ['@count' => count($nodes)]);
  }
}
