<?php

declare(strict_types=1);

namespace Drupal\myeventlane_rsvp\Service;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Url;

/**
 * Builds signed URLs for RSVP actions.
 */
final class RsvpLinkBuilder {

  public function __construct(
    private readonly CsrfTokenGenerator $csrf,
  ) {}

  /**
   * Create the cancel URL for an RSVP id.
   *
   * @param int $rsvp_id
   *   The RSVP entity (or legacy id) being cancelled.
   * @param bool $absolute
   *   Whether to return an absolute URL.
   */
  public function cancelUrl(int $rsvp_id, bool $absolute = FALSE): Url {
    $token = $this->csrf->get('myeventlane_rsvp:cancel:' . $rsvp_id);

    return Url::fromRoute('myeventlane_rsvp.cancel_form', ['rsvp' => $rsvp_id], [
      'query' => ['token' => $token],
      'absolute' => $absolute,
    ]);
  }

  /**
   * Convenience helper to return the final string.
   */
  public function cancelLinkString(int $rsvp_id, bool $absolute = TRUE): string {
    return $this->cancelUrl($rsvp_id, $absolute)->toString();
  }

}
