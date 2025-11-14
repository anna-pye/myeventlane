<?php

namespace Drupal\myeventlane_rsvp\Controller;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;

class RSVPExportController extends ControllerBase {
  public function export($event) {
    // Only allow vendors who own the event or admins.
    $account = $this->currentUser();
    $node = \Drupal\node\Entity\Node::load($event);
    if (!$node || $node->bundle() !== 'event' || !$account->isAuthenticated()) {
      throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
    }

    $response = new StreamedResponse(function() use ($event) {
      $handle = fopen('php://output', 'w');
      // Header
      fputcsv($handle, ['First Name', 'Last Name', 'Email', 'Comments', 'Submitted']);
      // Get RSVPs for this event
      $connection = \Drupal::database();
      $query = $connection->select('myeventlane_rsvp', 'r')
        ->fields('r', ['first_name', 'last_name', 'email', 'comments', 'created'])
        ->condition('event_nid', $event)
        ->orderBy('created', 'DESC');
      $results = $query->execute();
      foreach ($results as $record) {
        fputcsv($handle, [
          $record->first_name,
          $record->last_name,
          $record->email,
          $record->comments,
          \Drupal::service('date.formatter')->format($record->created, 'short'),
        ]);
      }
      fclose($handle);
    });

    $title = $node->label();
    $filename = 'rsvps-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower($title)) . '-' . date('Y-m-d') . '.csv';

    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    return $response;
  }
}
