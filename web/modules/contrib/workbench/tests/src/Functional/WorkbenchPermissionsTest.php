<?php

namespace Drupal\Tests\workbench\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests Workbench module permissions page.
 *
 * @group workbench
 */
class WorkbenchPermissionsTest extends BrowserTestBase {

  /**
   * The default theme.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Disabled config schema checking.
   *
   * @var bool
   *
   * @todo correct this later.
   */
  protected $strictConfigSchema = FALSE; // phpcs:ignore

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'workbench',
  ];

  /**
   * Tests that the module's permissions page is accessible.
   */
  public function testPermissionsPage() {
    $admin_user = $this->createUser([], 'administrator', TRUE);
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/people/permissions/module/workbench');

    $web_assert = $this->assertSession();
    $web_assert->pageTextContains('Module Permissions');
    $web_assert->pageTextContains('Access My Workbench');

  }

  /**
   * Tests that a user can access the My Workbench page w/ permission.
   */
  public function testWorkbenchPermissions() {
    $admin_user = $this->createUser([], 'administrator', TRUE);
    $this->drupalLogin($admin_user);

    // Create a custom content type 'test_custom_type'.
    $this->createContentType(['type' => 'test_custom_type', 'name' => 'Test']);

    // Create a user with permissions to access workbench.
    $auth_user = $this->drupalCreateUser([
      'access content',
      'access workbench',
      'access toolbar',
      'view the administration theme',
      'create test_custom_type content',
      'edit own test_custom_type content',
      'delete own test_custom_type content',
      'view the administration theme',
    ]);

    $this->drupalLogin($auth_user);

    // Perform assertions on workbench page.
    $this->drupalGet('/admin/workbench');

    $web_assert = $this->assertSession();
    $web_assert->pageTextContains('My Workbench');

    $web_assert->pageTextContains('Create content');
    $web_assert->pageTextContains('My edits');
    $web_assert->pageTextContains('All recent content');

  }

}
