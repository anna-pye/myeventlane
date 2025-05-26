<?php

namespace Drupal\Tests\block_visibility_groups\Functional;

use Drupal\block_visibility_groups\BlockVisibilityGroupInterface;

/**
 * Tests the block_visibility_groups Visibility Settings.
 *
 * @group block_visibility_groups
 */
class VisibilityTest extends BlockVisibilityGroupsTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['block', 'block_visibility_groups', 'node'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a Basic page node type.
    $this->drupalCreateContentType(
      [
        'type' => 'page',
        'name' => 'Basic page',
        'display_submitted' => FALSE,
      ]
    );
  }

  /**
   * Test single conditions.
   */
  public function testSingleConditions() {
    $group = $this->createGroup([
      [
        'id' => 'entity_bundle:node',
        'bundles' => ['page' => 'page'],
        'context_mapping' => [
          'node' => '@node.node_route_context:node',
        ],
        'negate' => FALSE,
      ],
    ]);

    // Assert node type condition.
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());

    // Block is rendered when expected.
    $page_node = $this->drupalCreateNode();
    $this->drupalGet($page_node->toUrl());
    $this->assertSession()->pageTextContains($block->label());

    // Block is NOT rendered when expected not to.
    $this->drupalGet('user');
    $this->assertSession()->pageTextNotContains($block->label());
    $block->delete();

    // Assert path condition.
    $this->deleteAllConditions($group);
    $group->addCondition([
      'id' => 'request_path',
      'pages' => '/node/*',
      'negate' => FALSE,
    ]);
    $group->save();
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());

    // Block is rendered when expected.
    $this->drupalGet($page_node->toUrl());
    $this->assertSession()->pageTextContains($block->label());

    // Block is not rendered when expected not to.
    $this->drupalGet('user');
    $this->assertSession()->pageTextNotContains($block->label());
    $block->delete();

    // Assert user condition.
    $this->deleteAllConditions($group);
    $group->addCondition([
      'id' => 'user_role',
      'roles' => ['authenticated'],
      'context_mapping' => [
        'user' => '@user.current_user_context:current_user',
      ],
      'negate' => FALSE,
    ]);
    $group->save();
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());

    // Block is rendered when expected.
    $this->drupalGet('user');
    $this->assertSession()->pageTextContains($block->label());

    // Block is not rendered when expected not to.
    $this->drupalLogout();
    $this->assertSession()->pageTextNotContains($block->label());

    // After uninstall conditions will not apply.
    $this->container->get('module_installer')->uninstall(['block_visibility_groups']);
    $this->drupalGet($page_node->toUrl());
    $this->assertSession()->pageTextContains($block->label());
    $this->drupalGet('user');
    $this->assertSession()->pageTextContains($block->label());
  }

  /**
   * Test multiple conditions with AND and OR operators.
   */
  public function testMultipleConditions() {
    // Operator AND (by default).
    $group = $this->createGroup([
      [
        'id' => 'request_path',
        'pages' => '/user/*',
        'negate' => FALSE,
      ],
      [
        'id' => 'user_role',
        'roles' => ['authenticated'],
        'context_mapping' => [
          'user' => '@user.current_user_context:current_user',
        ],
        'negate' => FALSE,
      ],
    ]);
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());
    $page_node = $this->drupalCreateNode();

    // Block is rendered when expected.
    $this->drupalGet('user');
    $this->assertPageTextContains($block->label());

    // Block is not rendered when expected not to.
    $this->drupalGet($page_node->toUrl());
    $this->assertSession()->pageTextNotContains($block->label());
    $this->drupalLogout();
    $this->drupalGet('user');
    $this->assertSession()->pageTextNotContains($block->label());
    $this->drupalGet($page_node->toUrl());
    $this->assertSession()->pageTextNotContains($block->label());

    // Operator OR.
    $group->setLogic('or');
    $group->save();

    // Block is rendered when expected.
    $this->drupalGet('user');
    $this->assertPageTextContains($block->label());
    $this->drupalLogin($this->blockVisibilityGroupsUser);
    $this->drupalGet($page_node->toUrl());
    $this->assertPageTextContains($block->label());
    $this->drupalGet('user');
    $this->assertPageTextContains($block->label());

    // Block is not rendered when expected not to.
    $this->drupalLogout();
    $this->drupalGet($page_node->toUrl());
    $this->assertSession()->pageTextNotContains($block->label());
  }

  /**
   * Helper to delete all conditions of a block visibility group.
   *
   * @param \Drupal\block_visibility_groups\BlockVisibilityGroupInterface $group
   *   BlockVisibilityGroup instance.
   */
  protected function deleteAllConditions(BlockVisibilityGroupInterface $group) {
    foreach ($group->getConditions() as $condition) {
      $group->removeCondition($condition->getConfiguration()['uuid']);
    }
    $group->save();
  }

}
