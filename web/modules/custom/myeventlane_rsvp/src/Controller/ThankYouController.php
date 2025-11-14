<?php

namespace Drupal\myeventlane_rsvp\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;
use Drupal\views\Views;

class ThankYouController extends ControllerBase {

  public function page(NodeInterface $event) {
    // Collect category tids for filtering (if present).
    $tids = [];
    if ($event->hasField('field_category') && !$event->get('field_category')->isEmpty()) {
      foreach ($event->get('field_category')->referencedEntities() as $term) {
        $tids[] = $term->id();
      }
    }

    // Try preferred view first; fall back to another if it doesn't exist.
    $view_name = 'recommended_for_you_affinity'; // you have this
    $display_id = 'block_1';                     // adjust if your display id differs
    $view = Views::getView($view_name);

    if (!$view) {
      // Fallback to 'trending_events' if the above doesn't exist.
      $view_name = 'trending_events';
      $display_id = 'block_1'; // adjust if needed
      $view = Views::getView($view_name);
    }

    // Build the render array for the view (only if found).
    $recommended = [];
    if ($view) {
      // Pass arguments if your view accepts them (example: comma separated tids, exclude nid).
      // If your view does not use contextual filters, set '#arguments' => [] or omit it.
      $args = [
        implode(',', $tids ?: ['_none']), // Arg 0: category tids or sentinel
        (string) $event->id(),            // Arg 1: current event nid (often used to exclude)
      ];

      $recommended = [
        '#type' => 'view',
        '#name' => $view_name,
        '#display_id' => $display_id,
        '#arguments' => $args,
      ];
    } else {
      // Friendly fallback message if no view exists at all.
      $recommended = [
        '#markup' => $this->t('More events will appear here soon.'),
      ];
    }

    // Action links.
    $home_link = [
      '#type' => 'link',
      '#title' => $this->t('Back to homepage'),
      '#url' => Url::fromRoute('<front>'),
      '#attributes' => ['class' => ['button', 'button--secondary']],
    ];
    $event_link = [
      '#type' => 'link',
      '#title' => $this->t('View your event'),
      '#url' => $event->toUrl(),
      '#attributes' => ['class' => ['button', 'button--primary']],
    ];

    return [
      '#theme' => 'myeventlane_rsvp_thankyou',
      '#event' => $event,
      '#actions' => [
        'event' => $event_link,
        'home'  => $home_link,
      ],
      '#recommended' => $recommended,
      '#attached' => [
        'library' => ['myeventlane_rsvp/thankyou'],
      ],
    ];
  }
}
