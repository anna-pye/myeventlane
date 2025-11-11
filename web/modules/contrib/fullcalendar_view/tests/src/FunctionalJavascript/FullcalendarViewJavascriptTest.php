<?php

namespace Drupal\Tests\fullcalendar_view\FunctionalJavascript;

/**
 * Tests the Fullcalendar View JavaScript functionality.
 *
 * @group fullcalendar_view
 */
class FullcalendarViewJavascriptTest extends FullcalendarViewJavascriptTestBase {

  /**
   * Tests the event title.
   */
  public function testEventTitle() {
    $date_format = 'Y-m-d\\TH:i:s';

    $title_1 = '<script>alert("Hi")</script>';
    $title_2 = "'Test Event & 2'";
    // Create events.
    $this->createEvent($title_1, date($date_format), date($date_format, strtotime('+1 hour')));
    $this->createEvent($title_2, date($date_format, strtotime('+3 hour')), date($date_format, strtotime('+4 hour')));

    $this->drupalGet('/fullcalendar-view-page');
    $assert = $this->assertSession();
    // Check that the calendar contains the events.
    $assert->waitForText($title_1);
    $assert->linkByHrefExists('/node/1');
    $assert->waitForText($title_2);
    $assert->linkByHrefExists('/node/2');

    $list_view = $assert->waitForButton('list');
    $list_view->click();
    $assert->pageTextContains($title_2);
  }

  /**
   * Tests adding event function.
   */
  public function testAddEvent() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/admin/structure/views/view/fullcalendar_view_page/edit/page_1');
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();
    // Set up the off-canvas function.
    $page->findLink('Settings')->click();
    $assert->waitForButton('Apply');
    $page->find('css', 'details[data-drupal-selector=edit-style-options-display]')->click();
    $page->findField('Create a new event via the Off-Canvas dialog.')->click();
    // Event bundle setting.
    $bundle_field = $assert->waitForField('Event bundle (Content) type');
    $bundle_field->setValue('event');
    $dialog_buttons = $page->find('css', '.ui-dialog-buttonset');
    $dialog_buttons->pressButton('Apply');
    $page->findButton('Save')->click();
    // Go to the calendar view.
    $this->drupalGet('/fullcalendar-view-page');
    $assert->waitForLink('Add event');
    $page->clickLink('Add event');
    // Check if the save button exists.
    $assert->waitForButton('Save');
    $assert->buttonExists('Save');
  }

}
