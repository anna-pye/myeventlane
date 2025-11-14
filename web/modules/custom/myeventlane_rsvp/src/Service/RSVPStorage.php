<?php

declare(strict_types=1);

namespace Drupal\myeventlane_rsvp\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Psr\Log\LoggerInterface;

/**
 * Thin storage layer for the myeventlane_rsvp table.
 */
final class RSVPStorage {

  public function __construct(
    private readonly Connection $db,
    private readonly AccountProxyInterface $currentUser,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Load an RSVP row by id.
   *
   * @return array<string,mixed>|null
   */
  public function load(int $id): ?array {
    $row = $this->db->select('myeventlane_rsvp', 'r')
      ->fields('r')
      ->condition('id', $id)
      ->execute()
      ->fetchAssoc();

    // Optional join to pull event title if you want to show it in the confirm.
    if ($row && !empty($row['event_nid'])) {
      $nid = (int) $row['event_nid'];
      $title = $this->db->select('node_field_data', 'n')
        ->fields('n', ['title'])
        ->condition('nid', $nid)
        ->condition('langcode', $row['langcode'] ?? 'en')
        ->execute()
        ->fetchField();
      if ($title) {
        $row['event_title'] = (string) $title;
      }
    }

    return $row ?: null;
  }

  /**
   * Cancel an RSVP (idempotent).
   */
  public function cancel(int $id, int $actorUid, string $reason = ''): void {
    $this->db->update('myeventlane_rsvp')
      ->fields([
        'status' => 'cancelled',
        'cancel_reason' => mb_substr($reason, 0, 255),
        'changed' => \Drupal::time()->getRequestTime(),
        'cancelled_by' => $actorUid,
      ])
      ->condition('id', $id)
      ->execute();

    $this->logger->notice('RSVP @id cancelled by UID @uid', ['@id' => $id, '@uid' => $actorUid]);
  }

}
