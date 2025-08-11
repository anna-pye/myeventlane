<?php

namespace Drupal\myeventlane_user\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\node\Entity\Node;
use Drupal\Core\Url;

class RsvpController {

  public function cancelRsvp($rsvp) {
    $node = Node::load($rsvp);
    if ($node && $node->bundle() === 'rsvp_submission') {
      $node->setUnpublished();
      $node->save();
    }

    \Drupal::messenger()->addMessage('Your RSVP was cancelled.');
    return new RedirectResponse(Url::fromRoute('myeventlane_user.my_events')->toString());
  }
}
