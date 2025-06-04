<?php

namespace Drupal\commerce_stripe;

/**
 * Defines the different states for a webhook event.
 */
enum WebhookEventState: int {

  case Unprocessed = 0;
  case Succeeded = 1;
  case Failed = 2;
  case Skipped = 3;

  /**
   * Returns the label.
   *
   * @return string
   *   The label.
   */
  public function label(): string {
    return match ($this) {
      self::Unprocessed => 'Unprocessed',
      self::Succeeded => 'Succeeded',
      self::Failed => 'Suspended',
      self::Skipped => 'Skipped',
    };
  }

}
