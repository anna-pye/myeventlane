<?php

namespace Drupal\Tests\workbench\Functional;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultNeutral;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests block install.
 *
 * @group workbench
 */
class WorkbenchBlockTest extends BrowserTestBase {

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
    'block',
    'workbench',
    'workbench_hooks_test',
  ];

  /**
   * Tests that the module can be installed and is visible to the admin.
   */
  public function testBlock() {
    $block = $this->placeBlock('workbench_block', [
      'label' => 'Workbench information',
    ]);

    // User with "access workbench" permission and anonymous session.
    $account = $this->drupalCreateUser([
      'access workbench',
    ]);
    $anonymous_account = new AnonymousUserSession();

    $this->drupalLogin($account);
    $this->drupalGet('<front>');

    $assert_session = $this->assertSession();

    // Block should be visible for the user.
    $assert_session->pageTextContains('Workbench information');

    // Block is not accessible without permission.
    $this->drupalLogout();
    $assert_session->pageTextNotContains('Workbench information');

    // Test access() method return type.
    $this->assertTrue($block->getPlugin()->access($account));
    $this->assertInstanceOf(AccessResultAllowed::class, $block->getPlugin()->access($account, TRUE));

    $this->assertFalse($block->getPlugin()->access($anonymous_account));
    $this->assertInstanceOf(AccessResultNeutral::class, $block->getPlugin()->access($anonymous_account, TRUE));

  }

}
