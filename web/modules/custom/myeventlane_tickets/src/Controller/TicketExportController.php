<?php

namespace Drupal\myeventlane_tickets\Controller;

use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

class TicketExportController extends ControllerBase {

  public function exportCsv($event) {
    $header = ['Ticket Holder', 'Email', 'Ticket Type'];
    $rows = [];

    // TODO: Replace this with real data lookup for paid tickets
    $rows[] = ['Jane Doe', 'jane@example.com', 'VIP'];
    $rows[] = ['John Smith', 'john@example.com', 'General'];

    $csv_content = implode(',', $header) . "\n";
    foreach ($rows as $row) {
      $csv_content .= implode(',', $row) . "\n";
    }

    $filename = 'ticket_attendees_event_' . $event . '.csv';
    $response = new Response($csv_content);
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

    return $response;
  }

}