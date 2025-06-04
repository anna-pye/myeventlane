<?php

namespace Drupal\Tests\fullcalendar_view\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Base class for Fullcalendar View functionality tests.
 *
 * @group fullcalendar_view
 */
abstract class FullcalendarViewTestBase extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'datetime',
    'node',
    'views',
    'views_ui',
    'field',
    'field_ui',
    'fullcalendar_view',
    'fullcalendar_test',
    'user',
  ];

  /**
   * The default theme to use for the test.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with permission to administer site configuration.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create and login as admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
      'administer views',
      'administer content types',
      'administer node fields',
      'administer nodes',
      'bypass node access',
      'access content',
    ]);

    // Create a content type for events.
    $this->drupalCreateContentType(['type' => 'event', 'name' => 'Event']);

    // Add date fields to the Event content type.
    $this->addField('event', 'field_start_date', 'datetime', 'Start Date');
    $this->addField('event', 'field_end_date', 'datetime', 'End Date');
  }

  /**
   * Creates an event node.
   *
   * @param string $title
   *   The title of the event.
   * @param string $start_date
   *   The start date of the event.
   * @param string $end_date
   *   The end date of the event.
   */
  protected function createEvent($title, $start_date, $end_date) {
    $node = $this->drupalCreateNode([
      'type' => 'event',
      'title' => $title,
      'field_start_date' => $start_date,
      'field_end_date' => $end_date,
    ]);
    $node->save();
  }

  /**
   * Helper function to add fields to a content type.
   *
   * @param string $bundle
   *   The bundle type (content type).
   * @param string $field_name
   *   The field machine name.
   * @param string $type
   *   The field type (e.g., 'text', 'datetime').
   * @param string $label
   *   The label for the field.
   */
  protected function addField($bundle, $field_name, $type, $label) {
    // Create field storage.
    $field_storage = FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $type,
    ]);
    $field_storage->save();

    // Create field instance.
    $field_instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $bundle,
      'label' => $label,
    ]);
    $field_instance->save();

    // Update the form display for this field.
    $form_display = EntityFormDisplay::load('node.' . $bundle . '.default');
    if (!$form_display) {
      $form_display = EntityFormDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $form_display->setComponent($field_name, [
      'type' => 'datetime_default',
    ])
      ->save();

    // Update the view display for this field.
    $view_display = EntityViewDisplay::load('node.' . $bundle . '.default');
    if (!$view_display) {
      $view_display = EntityViewDisplay::create([
        'targetEntityType' => 'node',
        'bundle' => $bundle,
        'mode' => 'default',
        'status' => TRUE,
      ]);
    }
    $view_display->setComponent($field_name, [
      'type' => 'datetime_default',
    ])
      ->save();
  }

}
