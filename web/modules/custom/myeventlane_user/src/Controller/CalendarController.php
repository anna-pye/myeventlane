<?php

namespace Drupal\myeventlane_user\Controller;

use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

class CalendarController extends ControllerBase {

  public function download($event_id) {
    $event = Node::load($event_id);

    if (!$event || $event->bundle() !== 'event') {
      return new Response('Invalid event.', 404);
    }

    // Event Title
    $title = $event->label() ?? 'MyEventLane Event';

    // Start/End Dates
    $start_raw = $event->get('field_start_date')->value;
    if (empty($start_raw)) {
      return new Response('Missing start date.', 400);
    }
    $end_raw = $event->get('field_end_date')->value ?? $start_raw;

    // Format for iCal
    $start = gmdate('Ymd\THis\Z', strtotime($start_raw));
    $end = gmdate('Ymd\THis\Z', strtotime($end_raw));

    // Venue Name
    $venue = trim($event->get('field_venue')->value ?? '');

    // Address String
    $address = '';
    $addr = $event->get('field_venue_address')->first();
    if ($addr) {
      $address = implode(', ', array_filter([
        $addr->getAddressLine1(),
        $addr->getLocality(),
        $addr->getAdministrativeArea(),
        $addr->getPostalCode(),
        $addr->getCountryCode(),
      ]));
    }

    $location = trim($venue . ' ' . $address) ?: 'Online';

    // Event URL
    $event_url = $event->toUrl('canonical', ['absolute' => TRUE])->toString();

    // ✅ Use the custom field_event_discriptionts for description
    $body = '';
    if ($event->hasField('field_event_discriptionts') && !$event->get('field_event_discriptionts')->isEmpty()) {
      $body = $event->get('field_event_discriptionts')->value;
    }

    $description = strip_tags($body);
    $description .= "\n\nMore info: " . $event_url;

    // iCal Body
    $ical = <<<ICAL
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//MyEventLane//EN
CALSCALE:GREGORIAN
METHOD:PUBLISH
BEGIN:VEVENT
UID:event-{$event_id}@myeventlane
DTSTAMP:$start
DTSTART:$start
DTEND:$end
SUMMARY:{$this->escapeText($title)}
URL:{$this->escapeText($event_url)}
DESCRIPTION:{$this->escapeText($description)}
LOCATION:{$this->escapeText($location)}
STATUS:CONFIRMED
END:VEVENT
END:VCALENDAR
ICAL;

    return new Response(
      $ical,
      200,
      [
        'Content-Type' => 'text/calendar; charset=utf-8',
        'Content-Disposition' => 'attachment; filename="event-' . $event_id . '.ics"',
      ]
    );
  }

  protected function escapeText($text) {
    $text = str_replace(['\\', ';', ',', "\n", "\r"], ['\\\\', '\;', '\,', '\\n', ''], $text);
    return trim($text);
  }
}
