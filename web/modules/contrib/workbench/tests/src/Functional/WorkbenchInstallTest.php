<?php

namespace Drupal\Tests\workbench\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests base install.
 *
 * @group workbench
 */
class WorkbenchInstallTest extends BrowserTestBase {

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
   * Tests that the module can be installed and is visible to the admin.
   */
  public function testInstall() {
    $admin_user = $this->createUser([], 'administrator', TRUE);
    $this->drupalLogin($admin_user);
    $this->drupalGet('admin/workbench');

    $web_assert = $this->assertSession();
    $web_assert->pageTextContains('My Workbench');

    $this->drupalGet('admin/config/workflow/workbench');
    $web_assert->pageTextContains('Workbench settings');
  }

}
