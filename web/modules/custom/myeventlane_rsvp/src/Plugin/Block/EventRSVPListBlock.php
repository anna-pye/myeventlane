<?php

namespace Drupal\myeventlane_rsvp\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Database\Database;
use Drupal\node\NodeInterface;
use Drupal\Core\Url;

/**
 * Provides a block to display RSVPs for a specific event.
 *
 * @Block(
 *   id = "event_rsvp_list_block",
 *   admin_label = @Translation("Event RSVP List"),
 * )
 */
class EventRSVPListBlock extends BlockBase {

  public function build() {
    $route_match = \Drupal::routeMatch();
    $node = $route_match->getParameter('node');

    if ($node instanceof NodeInterface && $node->bundle() === 'event') {
      $event_nid = $node->id();

      // Debug: log current event NID.
      \Drupal::logger('myeventlane_rsvp')->notice('Event page context: node ID: @nid', ['@nid' => $event_nid]);

      if (empty($event_nid)) {
        return [
          '#markup' => '<div class="event-rsvp-block-empty">No event context available.</div>',
          '#cache' => [
            'contexts' => ['route'],
          ],
        ];
      }

      // Build table header and rows.
      $header = [
        ['data' => $this->t('First Name')],
        ['data' => $this->t('Last Name')],
        ['data' => $this->t('Email')],
        ['data' => $this->t('Comments')],
        ['data' => $this->t('Submitted')],
      ];

      $rows = [];
      $connection = Database::getConnection();
      $query = $connection->select('myeventlane_rsvp', 'r')
        ->fields('r', ['first_name', 'last_name', 'email', 'comments', 'created'])
        ->condition('event_nid', $event_nid)
        ->orderBy('created', 'DESC');
      $results = $query->execute();

      foreach ($results as $record) {
        $rows[] = [
          $record->first_name,
          $record->last_name,
          $record->email,
          $record->comments,
          \Drupal::service('date.formatter')->format($record->created, 'short'),
        ];
      }
      if (empty($rows)) {
        $rows[] = [$this->t('No RSVPs yet for this event.'), '', '', '', ''];
      }

      $build = [];

      // Only show CSV download to event owner or admin.
      $current_user = \Drupal::currentUser();
      if ($current_user->id() == $node->getOwnerId() || $current_user->hasPermission('administer nodes')) {
        $build['download'] = [
          '#type' => 'link',
          '#title' => $this->t('Download RSVPs as CSV'),
          '#url' => Url::fromRoute('myeventlane_rsvp.export_csv', ['event' => $event_nid]),
          '#attributes' => [
            'class' => ['button', 'button--primary', 'rsvp-csv-download'],
            'download' => TRUE,
          ],
        ];
      }

      $build['table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#attributes' => ['class' => ['event-rsvp-table']],
      ];

      $build['#cache'] = [
        'contexts' => ['route'],
      ];

      return $build;
    }
    else {
      return [
        '#markup' => '<div class="event-rsvp-block-empty">No event context available.</div>',
        '#cache' => [
          'contexts' => ['route'],
        ],
      ];
    }
  }
}
