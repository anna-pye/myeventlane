<?php

namespace Drupal\myeventlane_dashboard\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

class VendorAnalyticsJsonController extends ControllerBase {

  public function data(): JsonResponse {
    $uid = $this->currentUser()->id();

    // Load vendor's events
    $event_ids = \Drupal::entityQuery('node')
      ->condition('type', 'event')
      ->condition('uid', $uid)
      ->accessCheck(TRUE)
      ->execute();

    if (empty($event_ids)) {
      return new JsonResponse([]);
    }

    $events = Node::loadMultiple($event_ids);
    $results = [];

    foreach ($events as $event) {
      $event_title = $event->label();
      $nid = $event->id();

      $rsvp_count = \Drupal::entityQuery('node')
        ->condition('type', 'rsvp_submission')
        ->condition('field_event', $nid)
        ->accessCheck(TRUE)
        ->count()
        ->execute();

      $sales_count = 0;

      $variation_ids = \Drupal::entityQuery('commerce_product_variation')
        ->condition('field_event', $nid)
        ->accessCheck(TRUE)
        ->execute();

      if (!empty($variation_ids)) {
        $sales_count = \Drupal::entityQuery('commerce_order_item')
          ->condition('purchased_entity', $variation_ids, 'IN')
          ->accessCheck(TRUE)
          ->count()
          ->execute();
      }

      $results[] = [
        'event' => $event_title,
        'rsvp' => $rsvp_count,
        'sales' => $sales_count,
      ];
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {
        $values = $form_state->getValues();

        $event_nid = $values['event_nid']; // However you're passing the Event node ID.
        // Other values as needed.

        $rsvp = Node::create([
          'type' => 'rsvp_submission',
          'title' => $values['name'], // or the label field you're using
          'field_event' => $event_nid, // This is the critical part!
          // ... set other fields as needed ...
        ]);
        $rsvp->save();
      }

    return new JsonResponse($results);
  }
}
