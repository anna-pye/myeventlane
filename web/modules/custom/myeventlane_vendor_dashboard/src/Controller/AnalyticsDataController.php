<?php

namespace Drupal\myeventlane_vendor_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\node\Entity\Node;

class AnalyticsDataController extends ControllerBase {

  /**
   * Return JSON analytics data for an event.
   */
  public function getAnalytics($event) {
    $nid = (int) $event;
    \Drupal::logger('analytics')->notice('RSVP results for event @nid: @count entries', [
      '@nid' => $nid,
      '@count' => count($data['labels']),
    ]);
    $data = [
      'labels' => [],
      'values' => [],
    ];

    $event_entity = Node::load($nid);
    if (!$event_entity) {
      return new JsonResponse($data);
    }

    // Access control: ensure current user can view analytics for this event.
    $uid = \Drupal::currentUser()->id();
    // Example check: event author or store owner logic here.
    if ($event_entity->getOwnerId() !== $uid) {
      return new JsonResponse($data);
    }

    // Example: Fetch RSVP counts by day.
    $query = \Drupal::database()->select('myeventlane_rsvp', 'r');
    $query->addExpression("DATE(FROM_UNIXTIME(r.timestamp))", 'day');
    $query->addExpression('COUNT(r.id)', 'cnt');
    $query->condition('r.event_nid', $nid);
    $query->groupBy('day');
    $query->orderBy('day', 'ASC');
    $results = $query->execute()->fetchAll();

    foreach ($results as $row) {
      $data['labels'][] = $row->day;
      $data['values'][] = (int) $row->cnt;
    }

    return new JsonResponse($data);
  }

}