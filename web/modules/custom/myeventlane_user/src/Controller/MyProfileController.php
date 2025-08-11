<?php

namespace Drupal\myeventlane_user\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\node\Entity\Node;
use Drupal\commerce_order\Entity\Order;
use Drupal\user\Entity\User;

/**
 * Controller for user dashboard at /my-profile.
 */
class MyProfileController extends ControllerBase {

  public function view() {
    $account = $this->currentUser();
    $uid = $account->id();
    $user = User::load($uid);
    $is_vendor = in_array('vendor', $user->getRoles());

    // Load RSVP submissions
    $connection = Database::getConnection();
    $query = $connection->select('myeventlane_rsvp', 'r')
      ->fields('r')
      ->condition('uid', $uid)
      ->orderBy('created', 'DESC');
    $result = $query->execute();
    $rsvp_rows = $result->fetchAllAssoc('id', \PDO::FETCH_ASSOC);

    $rsvps = [];
    foreach ($rsvp_rows as $id => $row) {
      $event = NULL;
      $image_url = NULL;

      if (!empty($row['event_nid']) && is_numeric($row['event_nid'])) {
        $event = Node::load($row['event_nid']);
        if ($event && $event->isPublished()) {
          $row['event'] = $event;
          $row['event_title'] = $event->label();

          // Event start date
          $row['event_date'] = $event->hasField('field_start_date') && !$event->get('field_start_date')->isEmpty()
            ? $event->get('field_start_date')->value
            : NULL;

          // Venue name (text field)
          $row['event_venue'] = $event->get('field_venue')->value ?? NULL;

          // Address (address field)
          $address = $event->get('field_venue_address')->first();
          if ($address) {
            $parts = array_filter([
              $address->getAddressLine1(),
              $address->getLocality(),
              $address->getAdministrativeArea(),
              $address->getPostalCode(),
            ]);
            $row['event_address'] = implode(', ', $parts);
          } else {
            $row['event_address'] = NULL;
          }

          // Image URL
          if ($event->hasField('field_image') && !$event->get('field_image')->isEmpty()) {
            $image_file = $event->get('field_image')->entity;
            if ($image_file) {
              $image_url = \Drupal::service('file_url_generator')->generateAbsoluteString($image_file->getFileUri());
              $row['event_image_url'] = $image_url;
            }
          }
        }
      }

      $rsvps[] = $row;
    }

    // Load Commerce orders for the user
    $orders = Order::loadMultiple();
    $user_orders = [];
    foreach ($orders as $order) {
      if ($order->getCustomerId() == $uid) {
        $user_orders[] = $order;
      }
    }

    return [
      '#markup' => '', // No markup needed, just trigger the page and preprocess.
      '#attached' => [
        'library' => ['myeventlane_theme/my-profile'],
      ],
    ];
  }

}
