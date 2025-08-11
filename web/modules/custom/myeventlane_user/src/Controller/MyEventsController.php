<?php

namespace Drupal\myeventlane_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\commerce_order\Entity\Order;
use Drupal\user\Entity\User;
use Drupal\Core\Link;
use Drupal\Core\Url;

class MyEventsController extends ControllerBase {

  public function dashboard() {
    $account = $this->currentUser();
    $uid = $account->id();

    // Load RSVP Submissions linked to this user
    $rsvps = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'type' => 'rsvp_submission',
        'uid' => $uid,
      ]);

    // Load commerce orders placed by this user
    $orders = Order::loadMultiple();
    $user_orders = [];
    foreach ($orders as $order) {
      if ($order->getCustomerId() == $uid) {
        $user_orders[] = $order;
      }
    }

    return [
      '#theme' => 'my_events_dashboard',
      '#rsvps' => $rsvps,
      '#orders' => $user_orders,
      '#attached' => [
        'library' => [
          'myeventlane_theme/my-events',
        ],
      ],
    ];
  }

}
