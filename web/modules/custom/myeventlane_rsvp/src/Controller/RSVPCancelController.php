<?php

namespace Drupal\myeventlane_rsvp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Core\Messenger\MessengerInterface;

class RSVPCancelController extends ControllerBase {

  public function cancel($rsvp_id) {
    $uid = $this->currentUser()->id();
    $connection = Database::getConnection();

    // Check that RSVP belongs to the user
    $query = $connection->select('myeventlane_rsvp', 'r')
      ->fields('r', ['id'])
      ->condition('id', $rsvp_id)
      ->condition('uid', $uid)
      ->range(0, 1);
    $existing = $query->execute()->fetchField();

    if ($existing) {
      $connection->delete('myeventlane_rsvp')
        ->condition('id', $rsvp_id)
        ->execute();
      $this->messenger()->addStatus($this->t('Your RSVP has been cancelled.'));
    }
    else {
      $this->messenger()->addError($this->t('Unable to cancel RSVP. It may not belong to you.'));
    }

    return new RedirectResponse(Url::fromRoute('<current>')->toString());
  }
}

