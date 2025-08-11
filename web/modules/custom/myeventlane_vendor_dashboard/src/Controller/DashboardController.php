<?php

namespace Drupal\myeventlane_vendor_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;
use Drupal\Core\Url;
use Drupal\Core\Link;

class DashboardController extends ControllerBase {

  public function dashboard() {
    $current_uid = \Drupal::currentUser()->id();

    // Get all events owned by the vendor.
    $event_ids = \Drupal::entityQuery('node')
      ->condition('type', 'event')
      ->condition('uid', $current_uid)
      ->accessCheck(TRUE)
      ->execute();

    $cards = [];

    if (!empty($event_ids)) {
      $events = Node::loadMultiple($event_ids);

      foreach ($events as $event) {
        $nid = $event->id();
        $title = $event->label();
        $ticket_type = $event->get('field_ticket_type')->value ?? 'RSVP';
        $ticket_limit = $event->get('field_ticket_limit')->value ?? '';
        $venue = $event->get('field_venue')->value ?? '';

        // Event image
        $image = '';
        if ($event->hasField('field_image') && !$event->get('field_image')->isEmpty()) {
          $image_entity = $event->get('field_image')->entity;
          if ($image_entity) {
            $image = [
              '#theme' => 'image_style',
              '#style_name' => '16_9_304x171_focal_point_webp',
              '#uri' => $image_entity->getFileUri(),
              '#alt' => $title,
              '#title' => $title,
              '#attributes' => ['class' => ['dashboard-card-image']],
            ];
          }
        }

        // RSVP count or ticket sales count
        $rsvp_count = 0;
        $ticket_sales_count = 0;
        if ($ticket_type === 'RSVP') {
          $rsvp_count = \Drupal::database()->select('myeventlane_rsvp', 'r')
            ->condition('event_nid', $nid)
            ->countQuery()
            ->execute()
            ->fetchField();
        }
        else {
          // Paid event: count tickets sold via Commerce.
          $variation_ids = \Drupal::entityQuery('commerce_product_variation')
            ->condition('field_event', $nid)
            ->accessCheck(TRUE)
            ->execute();
          if ($variation_ids) {
            $ticket_sales_count = \Drupal::database()->select('commerce_order_item', 'oi')
              ->fields('oi', [])
              ->condition('oi.purchased_entity', $variation_ids, 'IN')
              ->countQuery()
              ->execute()
              ->fetchField();
          }
        }

        // Download CSV button
        $download_csv = '';
        if (\Drupal::currentUser()->isAuthenticated()) {
          $download_csv = Link::fromTextAndUrl(
            $this->t('Download CSV'),
            Url::fromRoute('myeventlane_rsvp.export_csv', ['event' => $nid], ['attributes' => [
              'class' => ['button', 'button--primary', 'rsvp-csv-download'],
              'download' => TRUE,
            ]])
          )->toString();
        }

        // Analytics chart button
        $show_chart_btn = '<button class="button button--analytics js-analytics-btn-' . $nid . '" data-event-nid="' . $nid . '">Show Analytics</button>';
        $chart_placeholder = '<div id="analytics-chart-' . $nid . '" class="analytics-chart" style="display:none;"></div>';

        // "View RSVPs" button (change URL if needed)
        $view_rsvps_link = Link::fromTextAndUrl(
          $this->t('View RSVPs'),
          $event->toUrl('canonical'),
          ['attributes' => ['class' => ['button', 'button--secondary', 'view-rsvp-btn']]]
        )->toString();

        // Compose card render array.
        $cards[] = [
          '#theme' => 'myeventlane_vendor_dashboard_card',
          '#title' => $title,
          '#type' => $ticket_type,
          '#image' => $image,
          '#venue' => $venue,
          '#ticket_limit' => $ticket_limit,
          '#count' => $ticket_type === 'RSVP' ? $rsvp_count : $ticket_sales_count,
          '#download_csv' => [
            '#markup' => $download_csv,
          ],
          '#view_rsvps_link' => [
            '#markup' => $view_rsvps_link,
          ],
          '#show_chart_btn' => [
            '#markup' => $show_chart_btn . $chart_placeholder,
          ],
          '#nid' => $nid,
        ];
      }
    }

    return [
      '#theme' => 'myeventlane_vendor_dashboard',
      '#cards' => $cards,
      '#cache' => [
        'contexts' => ['user'],
        'max-age' => 0,
      ],
    ];
  }
}
