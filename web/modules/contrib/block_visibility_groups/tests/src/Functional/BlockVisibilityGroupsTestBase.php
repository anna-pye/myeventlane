<?php

namespace Drupal\Tests\block_visibility_groups\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\block_visibility_groups\Traits\BlockVisibilityGroupTrait;

/**
 * Base class for providing tests for block_visibility_groups.
 */
abstract class BlockVisibilityGroupsTestBase extends BrowserTestBase {

  use BlockVisibilityGroupTrait;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * User with permissions to administer block visibility groups.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $blockVisibilityGroupsUser;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['block', 'block_visibility_groups'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create and login with user who can administer blocks.
    $this->blockVisibilityGroupsUser = $this->drupalCreateUser([
      'administer blocks',
      'administer block visibility groups',
    ]);
    $this->drupalLogin($this->blockVisibilityGroupsUser);
  }

}
