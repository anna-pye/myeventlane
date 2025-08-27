<?php

namespace Drupal\myeventlane_rsvp\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Fetches normalized RSVP rows for a given user.
 */
final class UserRsvpRepository {

  public function __construct(
    private readonly Connection $db,
    private readonly EntityTypeManagerInterface $etm,
    private readonly EntityFieldManagerInterface $efm,
    private readonly DateFormatterInterface $dateFormatter,
    private readonly RsvpLinkBuilder $linkBuilder,
  ) {}

  /**
   * Return RSVPs for the given user id (newest first).
   *
   * @return array<int,array{
   *   id:int,
   *   event_id:int|null,
   *   event_title:string,
   *   event_url:\Drupal\Core\Url|null,
   *   event_start:int|null,
   *   event_start_fmt:string|null,
   *   month:string|null,
   *   day:string|null,
   *   image:array|null,
   *   status:string|null,
   *   cancel_url:string|null
   * }>
   */
  public function getUserRsvps(int $uid, int $limit = 100): array {
    $nodeRows = $this->tryNodeRsvps($uid, $limit);
    if (!empty($nodeRows)) {
      return $nodeRows;
    }

    foreach (['myeventlane_rsvp', 'myeventlane_rsvp_submissions'] as $table) {
      if ($this->db->schema()->tableExists($table)) {
        $rows = $this->tryCustomTable($table, $uid, $limit);
        if (!empty($rows)) {
          return $rows;
        }
      }
    }
    return [];
  }

  /**
   * Build a card image render array with graceful fallbacks.
   */
  private function buildCardImage(string $uri, string $alt): array {
    // Prefer responsive image style 'event_card' if present.
    $ri = $this->etm->getStorage('responsive_image_style')->load('event_card');
    if ($ri) {
      return [
        '#theme' => 'responsive_image',
        '#responsive_image_style_id' => 'event_card',
        '#uri' => $uri,
        '#attributes' => ['alt' => $alt, 'class' => ['mel-eventcard__img']],
      ];
    }
    // Fallback to classic image style.
    $is = $this->etm->getStorage('image_style')->load('event_card');
    if ($is) {
      return [
        '#theme' => 'image_style',
        '#style_name' => 'event_card',
        '#uri' => $uri,
        '#attributes' => ['alt' => $alt, 'class' => ['mel-eventcard__img']],
      ];
    }
    // Last resort: plain image.
    return [
      '#theme' => 'image',
      '#uri' => $uri,
      '#attributes' => ['alt' => $alt, 'class' => ['mel-eventcard__img']],
    ];
  }

  /**
   * Node-based storage (bundle: rsvp_submission).
   */
  private function tryNodeRsvps(int $uid, int $limit): array {
    // If the bundle doesn't exist, bail.
    try {
      $this->efm->getFieldDefinitions('node', 'rsvp_submission');
    }
    catch (\Throwable) {
      return [];
    }

    $storage = $this->etm->getStorage('node');
    $fields = $this->efm->getFieldDefinitions('node', 'rsvp_submission');

    $q = $storage->getQuery()->accessCheck(TRUE)
      ->condition('type', 'rsvp_submission')
      ->sort('created', 'DESC')
      ->range(0, $limit);

    // Prefer explicit user reference; fallback to node owner.
    if (isset($fields['field_user'])) {
      $q->condition('field_user.target_id', $uid);
    }
    else {
      $q->condition('uid', $uid);
    }

    $ids = $q->execute();
    if (!$ids) {
      return [];
    }

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $storage->loadMultiple($ids);
    $out = [];

    foreach ($nodes as $rsvp) {
      $id = (int) $rsvp->id();

      // Resolve event node if present.
      $event = NULL;
      if ($rsvp->hasField('field_event') && !$rsvp->get('field_event')->isEmpty()) {
        $event = $rsvp->get('field_event')->entity;
      }

      $eventId = $event instanceof NodeInterface ? (int) $event->id() : NULL;
      $eventTitle = $event instanceof NodeInterface ? ($event->label() ?: 'Event') : 'Event';
      $eventUrl = $eventId ? Url::fromRoute('entity.node.canonical', ['node' => $eventId]) : NULL;

      // Event start from event fields if available.
      $eventStart = NULL;
      if ($event instanceof NodeInterface) {
        if ($event->hasField('field_start_date') && !$event->get('field_start_date')->isEmpty()) {
          $eventStart = strtotime((string) $event->get('field_start_date')->value) ?: NULL;
        }
        elseif ($event->hasField('field_event_date') && !$event->get('field_event_date')->isEmpty()) {
          $eventStart = strtotime((string) $event->get('field_event_date')->value) ?: NULL;
        }
      }
      $eventStartFmt = $eventStart ? $this->dateFormatter->format($eventStart, 'custom', 'D j M Y, g:ia') : NULL;
      $month = $eventStart ? strtoupper($this->dateFormatter->format($eventStart, 'custom', 'M')) : NULL;
      $day = $eventStart ? $this->dateFormatter->format($eventStart, 'custom', 'j') : NULL;

      // Status if present on RSVP.
      $status = NULL;
      if ($rsvp->hasField('field_status') && !$rsvp->get('field_status')->isEmpty()) {
        $status = (string) $rsvp->get('field_status')->value;
      }

      // Optional image from event field.
      $image = NULL;
      if ($event instanceof NodeInterface && $event->hasField('field_image') && !$event->get('field_image')->isEmpty()) {
        $file = $event->get('field_image')->entity;
        if ($file) {
          $image = $this->buildCardImage($file->getFileUri(), $eventTitle);
        }
      }

      $cancel = $this->linkBuilder->cancelLinkString($id, TRUE);

      $out[] = [
        'id' => $id,
        'event_id' => $eventId,
        'event_title' => $eventTitle,
        'event_url' => $eventUrl,
        'event_start' => $eventStart,
        'event_start_fmt' => $eventStartFmt,
        'month' => $month,
        'day' => $day,
        'image' => $image,
        'status' => $status,
        'cancel_url' => $cancel,
      ];
    }

    return $out;
  }

  /**
   * Custom-table storage (auto-detects common column names).
   */
  private function tryCustomTable(string $table, int $uid, int $limit): array {
    $schema = $this->db->schema();

    $idCol = $this->firstExisting($schema, $table, ['id', 'rsvp_id']);
    if (!$idCol) return [];

    $uidCol = $this->firstExisting($schema, $table, ['uid', 'user_id']);
    $emailCol = $this->firstExisting($schema, $table, ['email', 'mail']);
    $eventCol = $this->firstExisting($schema, $table, ['event_nid', 'event_id', 'nid']);
    $statusCol = $this->firstExisting($schema, $table, ['status', 'state']);
    $createdCol = $this->firstExisting($schema, $table, ['created', 'created_at']);
    $startCol = $this->firstExisting($schema, $table, ['event_start', 'start_at', 'start_time']);

    $q = $this->db->select($table, 'r')->range(0, $limit)->orderBy($createdCol ?: $idCol, 'DESC');
    $q->addField('r', $idCol, 'id');

    if ($uidCol) {
      $q->condition("r.$uidCol", $uid);
    }
    elseif ($emailCol) {
      $account = \Drupal::currentUser();
      $user = $this->etm->getStorage('user')->load($account->id());
      $mail = $user?->getEmail();
      if (!$mail) return [];
      $q->condition("r.$emailCol", $mail);
    }
    else {
      return [];
    }

    if ($eventCol)  $q->addField('r', $eventCol, 'event_id');
    if ($statusCol) $q->addField('r', $statusCol, 'status');
    if ($createdCol) $q->addField('r', $createdCol, 'created');
    if ($startCol)   $q->addField('r', $startCol, 'event_start');

    $rows = $q->execute()->fetchAllAssoc('id');
    if (!$rows) return [];

    // Resolve event nodes for titles/urls/images if event_id present.
    $eventTitles = [];
    $eventUrls = [];
    $eventStarts = [];
    $eventImages = [];

    $eventIds = array_values(array_unique(array_filter(array_map(
      static fn($r) => isset($r->event_id) ? (int) $r->event_id : 0,
      $rows
    ))));

    if ($eventIds) {
      /** @var \Drupal\node\NodeInterface[] $events */
      $events = $this->etm->getStorage('node')->loadMultiple($eventIds);
      foreach ($events as $e) {
        $eid = (int) $e->id();
        $eventTitles[$eid] = $e->label() ?: 'Event';
        $eventUrls[$eid] = Url::fromRoute('entity.node.canonical', ['node' => $eid]);

        $start = NULL;
        if ($e->hasField('field_start_date') && !$e->get('field_start_date')->isEmpty()) {
          $start = strtotime((string) $e->get('field_start_date')->value) ?: NULL;
        }
        elseif ($e->hasField('field_event_date') && !$e->get('field_event_date')->isEmpty()) {
          $start = strtotime((string) $e->get('field_event_date')->value) ?: NULL;
        }
        $eventStarts[$eid] = $start;

        if ($e->hasField('field_image') && !$e->get('field_image')->isEmpty()) {
          $file = $e->get('field_image')->entity;
          if ($file) {
            $eventImages[$eid] = $this->buildCardImage($file->getFileUri(), $e->label() ?: 'Event');
          }
        }
      }
    }

    $out = [];
    foreach ($rows as $r) {
      $id = (int) $r->id;
      $eid = isset($r->event_id) ? (int) $r->event_id : NULL;

      $eventStart = NULL;
      if (isset($r->event_start) && is_numeric($r->event_start)) {
        $eventStart = (int) $r->event_start;
      }
      elseif ($eid && array_key_exists($eid, $eventStarts)) {
        $eventStart = $eventStarts[$eid];
      }

      $eventStartFmt = $eventStart ? $this->dateFormatter->format($eventStart, 'custom', 'D j M Y, g:ia') : NULL;
      $month = $eventStart ? strtoupper($this->dateFormatter->format($eventStart, 'custom', 'M')) : NULL;
      $day = $eventStart ? $this->dateFormatter->format($eventStart, 'custom', 'j') : NULL;

      $out[] = [
        'id' => $id,
        'event_id' => $eid,
        'event_title' => $eid && isset($eventTitles[$eid]) ? $eventTitles[$eid] : 'Event',
        'event_url' => $eid && isset($eventUrls[$eid]) ? $eventUrls[$eid] : NULL,
        'event_start' => $eventStart,
        'event_start_fmt' => $eventStartFmt,
        'month' => $month,
        'day' => $day,
        'image' => $eid && isset($eventImages[$eid]) ? $eventImages[$eid] : NULL,
        'status' => isset($r->status) ? (string) $r->status : NULL,
        'cancel_url' => $this->linkBuilder->cancelLinkString($id, TRUE),
      ];
    }

    return $out;
  }

  private function firstExisting($schema, string $table, array $candidates): ?string {
    foreach ($candidates as $col) {
      if ($schema->fieldExists($table, $col)) {
        return $col;
      }
    }
    return NULL;
  }
}
