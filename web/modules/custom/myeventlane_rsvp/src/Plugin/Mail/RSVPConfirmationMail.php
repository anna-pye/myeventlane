<?php

namespace Drupal\myeventlane_rsvp\Plugin\Mail;

use Drupal\Core\Mail\MailInterface;

/**
 * Defines RSVP confirmation mail plugin.
 *
 * @Mail(
 *   id = "confirmation",
 *   label = @Translation("RSVP Confirmation"),
 *   description = @Translation("Sends a confirmation email on RSVP.")
 * )
 */
class RSVPConfirmationMail implements MailInterface {
  public function format(array $message) {
    $message['subject'] = $message['params']['subject'];
    $message['body'][] = $message['params']['body'];
  }

  public function mail(array $message) {
    return TRUE;
  }
}
