<?php

namespace Drupal\myeventlane_checkout_paragraph\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Simple vendor controller to show attendee details for an order.
 */
class VendorOrderController extends ControllerBase {

  /**
   * Show attendee details for a single order.
   *
   * Route: myeventlane_checkout_paragraph.vendor_attendees
   *
   * @param \Drupal\commerce_order\Entity\Order $commerce_order
   *   The order entity passed by the route (parameter name: commerce_order).
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Render array or redirect on access denied.
   */
  public function attendees(Order $commerce_order) {
    $current_user = $this->currentUser();

    // Basic access: allow if admin; otherwise only allow if user is vendor/owner of
    // any event that appears in this order's purchased variations.
    if (!$current_user->hasPermission('administer nodes')) {
      // Check each purchased variation -> event owner.
      $allowed = FALSE;
      foreach ($commerce_order->getItems() as $item) {
        $variation = $item->getPurchasedEntity();
        if ($variation && $variation->hasField('field_event') && !$variation->get('field_event')->isEmpty()) {
          $event = $variation->get('field_event')->entity;
          if ($event && $event->getOwnerId() === $current_user->id()) {
            $allowed = TRUE;
            break;
          }
        }
      }
      if (!$allowed) {
        $this->messenger()->addError($this->t('You do not have access to view these attendees.'));
        return $this->redirect('<front>');
      }
    }

    // Build table rows: group by event title.
    $rows = [];
    foreach ($commerce_order->getItems() as $item) {
      $variation = $item->getPurchasedEntity();
      $event_title = $this->t('Unlinked Event');
      $event_id = NULL;
      if ($variation && $variation->hasField('field_event') && !$variation->get('field_event')->isEmpty()) {
        $event = $variation->get('field_event')->entity;
        if ($event) {
          $event_title = $event->label();
          $event_id = $event->id();
        }
      }

      // For each referenced ticket-holder paragraph, get core fields and extras.
      if ($item->hasField('field_ticket_holder')) {
        foreach ($item->get('field_ticket_holder')->referencedEntities() as $para) {
          $first = $para->hasField('field_first_name') ? $para->get('field_first_name')->value : '';
          $last = $para->hasField('field_last_name') ? $para->get('field_last_name')->value : '';
          $email = $para->hasField('field_email') ? $para->get('field_email')->value : '';

          // Gather extra responses (if any) — attempt to stringify them.
          $extras = [];
          if ($para->hasField('field_attendee_questions') && !$para->get('field_attendee_questions')->isEmpty()) {
            foreach ($para->get('field_attendee_questions')->referencedEntities() as $q) {
              // Try to read stored response(s) from this paragraph:
              // If your implementation saves responses to e.g. field_attendee_responses or similar,
              // please adapt here. We'll attempt to read the rendered value from fields
              // on the question paragraph for debugging (this may need to be adjusted).
              $label = $q->get('field_field_label')->value ?? ($q->get('field_label')->value ?? 'Question');
              $extras[] = $label;
            }
          }

          $rows[] = [
            'event_title' => $event_title,
            'event_id' => $event_id,
            'item_label' => $item->label(),
            'first_name' => $first,
            'last_name' => $last,
            'email' => $email,
            'extras' => implode(', ', $extras),
          ];
        }
      }
    }

    // Build render array: grouped by event.
    $grouped = [];
    foreach ($rows as $r) {
      $key = $r['event_title'] . '|' . ($r['event_id'] ?? '0');
      $grouped[$key][] = $r;
    }

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['vendor-attendees']],
      'title' => [
        '#type' => 'markup',
        '#markup' => '<h2>' . $this->t('Attendee Details for Order @order', ['@order' => $commerce_order->getOrderNumber()]) . '</h2>',
      ],
    ];

    foreach ($grouped as $key => $items) {
      [$event_title, $event_id] = explode('|', $key, 2);
      $build[] = [
        '#type' => 'details',
        '#open' => TRUE,
        '#title' => $event_title,
        'export_link' => $event_id ? [
          '#type' => 'link',
          '#title' => $this->t('Export CSV for this event'),
          '#url' => Url::fromRoute('myeventlane_checkout_paragraph.export_csv', ['event' => $event_id]),
          '#attributes' => ['class' => ['button', 'button--small']],
        ] : [],
        'table' => [
          '#type' => 'table',
          '#header' => [$this->t('Ticket'), $this->t('First name'), $this->t('Last name'), $this->t('Email'), $this->t('Extras')],
          '#rows' => array_map(function ($r) {
            return [$r['item_label'], $r['first_name'], $r['last_name'], $r['email'], $r['extras']];
          }, $items),
        ],
      ];
    }

    return $build;
  }

}