<?php

declare(strict_types=1);

namespace Drupal\Tests\lb_styles_conditions\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Tests\BrowserTestBase;
use Drupal\Core\Url;

/**
 * Tests the LB Styles conditions settings form.
 *
 * @group lb_styles_conditions
 */
class LbStylesConditionsSettingsTest extends BrowserTestBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'conditions_helper',
    'lb_styles_conditions',
    'layout_builder_styles',
    'layout_builder',
    'block_content',
    'node',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The admin user that will be created.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a user with permissions to manage lb styles conditions.
    $this->adminUser = $this->drupalCreateUser([
      'administer lb_styles_conditions',
      'configure any layout',
    ]);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests the admin settings form.
   */
  public function testSettingsForm(): void {
    // Navigate to the admin form.
    $this->drupalGet(Url::fromRoute('lb_styles_conditions.settings'));
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Layout Builder Styles Conditions settings');

    // Test that the form contains checkboxes for core conditions.
    // We don't have a specific 'test' condition in this module yet.
    $this->assertSession()->fieldExists('enabled_conditions[request_path]');
    $this->assertSession()->fieldExists('enabled_conditions[user_role]');
    // Add more core condition checks if needed, e.g., current_theme.
    $this->assertSession()->fieldExists('enabled_conditions[current_theme]');

    // Select some conditions and submit the form.
    $edit = [
      'enabled_conditions[user_role]' => 'user_role',
      'enabled_conditions[current_theme]' => 'current_theme',
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify that the settings were saved correctly by checking config.
    $config = $this->config('lb_styles_conditions.settings');
    $enabled_conditions = $config->get('enabled_conditions');
    $this->assertIsArray($enabled_conditions);
    $this->assertEquals('user_role', $enabled_conditions['user_role']);
    $this->assertEquals('current_theme', $enabled_conditions['current_theme']);
    $this->assertArrayNotHasKey('request_path', $enabled_conditions);

    // We cannot easily test the effect on the layout builder UI itself
    // in a simple functional test without more complex setup (like enabling
    // layout builder for a node type, creating a node, adding blocks, etc.).
    // This test focuses on verifying the settings form saves correctly.
    // A Kernel test might be better suited to check the condition filtering
    // logic directly if needed. Now update settings to disable a condition.
    $this->drupalGet(Url::fromRoute('lb_styles_conditions.settings'));
    $edit = [
      'enabled_conditions[user_role]' => 'user_role',
      // Uncheck current_theme.
      'enabled_conditions[current_theme]' => FALSE,
    ];
    $this->submitForm($edit, 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Verify the updated config.
    $config = $this->config('lb_styles_conditions.settings');
    $enabled_conditions = $config->get('enabled_conditions');
    $this->assertIsArray($enabled_conditions);
    $this->assertEquals('user_role', $enabled_conditions['user_role']);
    $this->assertArrayNotHasKey('current_theme', $enabled_conditions);
    $this->assertArrayNotHasKey('request_path', $enabled_conditions);
  }

}
