<?php

namespace Drupal\Tests\fullcalendar_view\FunctionalJavascript;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Provides a base class for Fullcalendar view JavaScript tests.
 */
abstract class FullcalendarViewJavascriptTestBase extends WebDriverTestBase {

  /**
   * The default theme used during test execution.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * The admin user account used in tests.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->createEventContentType();
    $this->adminUser = $this->createAdminUser();
  }

  /**
   * Creates the "event" content type and required fields.
   */
  protected function createEventContentType() {
    // Create a new content type for events if it doesn't exist.
    if (!NodeType::load('event')) {
      $event_type = NodeType::create([
        'type' => 'event',
        'name' => 'Event',
      ]);
      $event_type->save();

      // Add a start date field to the event content type.
      FieldStorageConfig::create([
        'field_name' => 'field_start_date',
        'entity_type' => 'node',
        'type' => 'datetime',
      ])->save();

      FieldConfig::create([
        'field_name' => 'field_start_date',
        'entity_type' => 'node',
        'bundle' => 'event',
        'label' => 'Start Date',
      ])->save();

      // Add an end date field to the event content type.
      FieldStorageConfig::create([
        'field_name' => 'field_end_date',
        'entity_type' => 'node',
        'type' => 'datetime',
      ])->save();

      FieldConfig::create([
        'field_name' => 'field_end_date',
        'entity_type' => 'node',
        'bundle' => 'event',
        'label' => 'End Date',
      ])->save();
    }
  }

  /**
   * Creates an event for testing.
   *
   * @param string $title
   *   The title of the event.
   * @param string $start
   *   The start date of the event in 'Y-m-d\TH:i:s' format.
   * @param string $end
   *   The end date of the event in 'Y-m-d\TH:i:s' format.
   */
  protected function createEvent($title, $start, $end) {
    $event = [
      'type' => 'event',
      'title' => $title,
      'field_start_date' => $start,
      'field_end_date' => $end,
    ];
    $this->drupalCreateNode($event);
  }

  /**
   * Creates an admin user with necessary permissions.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The admin user account.
   */
  protected function createAdminUser() {
    // Define the permissions required by the admin user for the tests.
    $permissions = [
      'administer site configuration',
      'administer content types',
      'bypass node access',
      'administer nodes',
    ];

    return $this->drupalCreateUser($permissions, 'admin_user', TRUE);
  }

}
