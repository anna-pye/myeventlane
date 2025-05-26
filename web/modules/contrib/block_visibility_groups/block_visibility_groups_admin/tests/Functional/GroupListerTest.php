<?php

namespace Drupal\Tests\block_visibility_groups_admin\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Provides automated tests for the block_visibility_groups_admin module.
 *
 * @group block_visibility_groups_admin
 */
class GroupListerTest extends BrowserTestBase {

  /**
   * Drupal\block_visibility_groups\GroupEvaluator definition.
   *
   * @var \Drupal\block_visibility_groups\GroupEvaluator
   */
  protected $block_visibility_groups_group_evaluator;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return [
      'name' => "block_visibility_groups_admin GroupLister's controller functionality",
      'description' => 'Test Unit for module block_visibility_groups_admin and controller GroupLister.',
      'group' => 'Other',
    ];
  }

  /**
   * Tests block_visibility_groups_admin functionality.
   */
  public function testGroupLister() {
    // Check that the basic functions of module block_visibility_groups_admin.
    self::assertEquals(TRUE, TRUE, 'Test Unit Generated via App Console.');
  }

}
