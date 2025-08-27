<?php

namespace Drupal\myeventlane_rsvp\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\UrlHelper;

class RsvpRedirectResolver {
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $etm,
  ) {}

  /**
   * Resolve the Thank You URL for an event.
   *
   * @param int $event_nid
   * @param array $context e.g. ['medium' => 'rsvp'|'ticket']
   */
  public function resolveForEvent(int $event_nid, array $context = []): Url {
    $cfg = $this->configFactory->get('myeventlane_rsvp.settings')->get('thank_you') ?? [];
    $use_field  = !empty($cfg['use_event_field']);
    $field_name = $cfg['event_field'] ?? 'field_thank_you_path';
    $default    = $cfg['default_path'] ?? '/thanks';
    $utm        = $cfg['utm'] ?? [];
    $medium     = $context['medium'] ?? 'rsvp';

    $target = '';

    // Try event field if enabled and present.
    if ($use_field) {
      $event = $this->etm->getStorage('node')->load($event_nid);
      if ($event && $event->hasField($field_name)) {
        $raw = (string) ($event->get($field_name)->value ?? '');
        $target = trim($raw);
      }
    }

    if ($target === '') {
      $target = $default;
    }

    // Build Url object (absolute URL vs internal path).
    if (UrlHelper::isValid($target, TRUE)) {
      $url = Url::fromUri($target, ['absolute' => TRUE]);
    }
    else {
      // Force internal path to start with '/'.
      if ($target === '' || $target[0] !== '/') {
        $target = '/' . ltrim($target, '/');
      }
      $url = Url::fromUserInput($target, ['absolute' => TRUE]);
    }

    // UTM tagging (best practice defaults).
    if (!empty($utm['enabled'])) {
      $campaign = strtr($utm['campaign'] ?? 'event_{nid}', [
        '{nid}' => (string) $event_nid,
      ]);
      $query = array_merge($url->getOption('query') ?? [], [
        'utm_source'   => (string) ($utm['source'] ?? 'myeventlane'),
        'utm_medium'   => (string) ($utm['medium'] ?? $medium),
        'utm_campaign' => $campaign,
      ]);
      $url = $url->setOption('query', $query);
    }

    return $url;
  }
}
