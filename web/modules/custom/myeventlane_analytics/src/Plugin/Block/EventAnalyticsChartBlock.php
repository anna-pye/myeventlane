<?php

namespace Drupal\myeventlane_analytics\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\views\Views;

/**
 * Provides a block for the per-event analytics React chart.
 *
 * @Block(
 *   id = "event_analytics_chart_block",
 *   admin_label = @Translation("Event Analytics Chart Block")
 * )
 */
class EventAnalyticsChartBlock extends BlockBase {
  public function build() {
    // Use Views to get analytics data in array for JS.
    $view = Views::getView('event_analytics');
    $view->setDisplay('default');
    $view->execute();
    $data = [];
    foreach ($view->result as $row) {
      $data[] = [
        'event' => isset($row->_entity) ? $row->_entity->label() : '',
        'tickets' => isset($row->field_ticket_count) ? (int) $row->field_ticket_count : 0,
        'rsvps' => isset($row->field_rsvp_submission_count) ? (int) $row->field_rsvp_submission_count : 0,
          ];
    }
    return [
      '#theme' => 'block__event_analytics_chart',
      '#attached' => [
        'library' => ['myeventlane_analytics/event_analytics_chart'],
        'drupalSettings' => [
          'myeventlane_analytics' => [
            'chartData' => $data,
          ],
        ],
      ],
    ];
  }
}
