<?php
namespace Drupal\myeventlane_rsvp;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\RendererInterface;

class RSVPEmailService {

  protected $mailManager;
  protected $renderer;

  public function __construct(MailManagerInterface $mailManager, RendererInterface $renderer) {
    $this->mailManager = $mailManager;
    $this->renderer = $renderer;
  }

  public function sendConfirmation(EntityInterface $entity) {
    if ($entity->hasField('field_email')) {
      $to = $entity->get('field_email')->value;
      $params = [
        'subject' => 'Your RSVP Confirmation',
        'body' => 'Thanks for RSVPing to our event!',
      ];
      $this->mailManager->mail('myeventlane_rsvp', 'confirmation', $to, 'en', $params);
    }
  }
}
