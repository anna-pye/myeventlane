<?php

namespace Drupal\Tests\fullcalendar_view\Functional;

/**
 * Tests the Fullcalendar View functionality.
 *
 * @group fullcalendar_view
 */
class FullcalendarViewTest extends FullcalendarViewTestBase {

  /**
   * Tests the Fullcalendar event title.
   */
  public function testFullcalendarViewTitle() {
    $date_format = 'Y-m-d\\TH:i:s';
    // Test event title without html tag.
    $this->createEvent('Test Event 1', date($date_format), date($date_format, strtotime('+1 hour')));
    $this->createEvent('Test Event 2', date($date_format, strtotime('+3 hour')), date($date_format, strtotime('+4 hour')));

    $assert = $this->assertSession();
    // Ensure the Fullcalendar view page exists and loads.
    $this->drupalGet('/fullcalendar-view-page');
    $assert->statusCodeEquals(200);

    // Check that the calendar is displayed.
    $assert->pageTextContains('Fullcalendar');

    // Check that the events are displayed on the calendar.
    $assert->responseContains('Test Event 1');
    $assert->responseContains('Test Event 2');
  }

}
