<?php

namespace Drupal\Tests\block_visibility_groups\Functional;

use Drupal\block_visibility_groups\Entity\BlockVisibilityGroup;
use Drupal\Core\Url;

/**
 * Tests the block_visibility_groups UI.
 *
 * @group block_visibility_groups
 */
class BlockVisibilityGroupsUiTest extends BlockVisibilityGroupsTestBase {

  /**
   * Test adding a block to a visibility group through the UI.
   */
  public function testAddBlockToGroup() {
    $group = $this->createGroup();

    /** @var \Drupal\block\Entity\Block $block */
    $block = $this->drupalPlaceBlock('system_powered_by_block', [
      'label_display' => 'visible',
      'label' => $this->randomMachineName(),
    ]);

    $this->drupalGet('admin/structure/block/manage/' . $block->id());

    $select = $this->assertSession()->selectExists('visibility[condition_group][block_visibility_group]');
    $select->selectOption($group->id());

    $this->getSession()->getPage()->pressButton('Save block');

    $this->drupalGet('admin/structure/block/manage/' . $block->id());
    $selected = $this->assertSession()->elementExists('css', '#edit-visibility-condition-group-block-visibility-group option:selected');
    self::assertEquals($selected->getText(), $group->label());

    $block = \Drupal::service('entity_type.manager')
      ->getStorage('block')
      ->load($block->id());

    $actual = $block->getVisibility();
    self::assertEquals($actual['condition_group']['block_visibility_group'], $group->id());
  }

  /**
   * Test the creation of block visibility groups.
   */
  public function testBlockVisibilityCreation() {
    // Enable action and task blocks.
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('local_tasks_block');

    // Test block visibility tab exists and works.
    $this->drupalGet('admin/structure/block');
    $this->assertSession()->linkExists('Block Visibility Groups');
    $this->getSession()->getPage()->clickLink('Block Visibility Groups');
    $this->assertSession()->statusCodeEquals(200);

    // Test add block visibility button exists and works.
    $this->assertSession()->linkExists('Add Block Visibility Group');
    $this->getSession()->getPage()->clickLink('Add Block Visibility Group');
    $this->assertSession()->statusCodeEquals(200);

    // Fill and submit form for block visibility groups creation.
    $this->assertSession()->fieldExists('edit-label');
    $edit = [
      'label' => $this->randomMachineName(),
      'id' => 'test_block_visibility_groups',
    ];
    $this->submitForm($edit, 'Save');

    // Block visibility created successfully or not.
    $this->assertPageTextContains('Saved the @group Block Visibility Group.', ['@group' => $edit['label']]);
    $block_visibility_group = \Drupal::entityTypeManager()
      ->getStorage('block_visibility_group')
      ->load($edit['id']);

    self::assertEquals($block_visibility_group->id(), $edit['id']);
    self::assertEquals($block_visibility_group->label(), $edit['label']);
  }

  /**
   * Test the editing of block visibility groups.
   */
  public function testGroupEditPage() {
    $group = $this->createGroup();
    $this->drupalGet('admin/structure/block/block-visibility-group/' . $group->id());

    // Add condition.
    $this->getSession()->getPage()->clickLink('Add new condition');
    $this->assertPageTextContains('Request Path');
    $this->assertPageTextContains('Current Theme');
    $this->assertPageTextContains('User Role');

    $this->getSession()->getPage()->clickLink('User Role');
    $this->assertPageTextContains('Anonymous user');
    $this->assertPageTextContains('Authenticated user');
    $this->assertPageTextContains('Negate the condition');
    $this->getSession()->getPage()->checkField('Anonymous user');
    $this->getSession()->getPage()->pressButton('Add condition');
    $this->assertPageTextContains('The @condition condition has been added.', ['@condition' => 'User Role']);

    $group = BlockVisibilityGroup::load($group->id());
    /** @var \Drupal\Core\Condition\ConditionPluginCollection $conditions */
    $conditions = $group->getConditions();
    self::assertCount(1, $conditions);
    $ids = $conditions->getInstanceIds();
    $condition_id = end($ids);

    $row = $this->assertConditionConfigInGroupUi($condition_id, [
      'label' => 'User Role',
      'description' => 'The user is a member of Anonymous user',
    ]);

    // Edit condition.
    $row->clickLink('Edit');
    $this->getSession()->getPage()->checkField('Authenticated user');
    $this->getSession()->getPage()->checkField('Negate the condition');
    $this->getSession()->getPage()->pressButton('Update condition');
    $this->assertPageTextContains('The @condition condition has been updated.', ['@condition' => 'User Role']);

    $this->assertConditionConfigInGroupUi($condition_id, [
      'label' => 'User Role',
      'description' => 'The user is not a member of Anonymous user, Authenticated user',
    ]);

    // Delete condition.
    $row->clickLink('Delete');
    $this->assertPageTextContains('Are you sure you want to delete the condition @condition?', ['@condition' => 'User Role']);
    $this->getSession()->getPage()->pressButton('Delete');
    $this->assertPageTextContains('The condition @condition has been removed.', ['@condition' => 'User Role']);
    $this->assertPageTextContains('There are no conditions.');

    // Assert default group configuration.
    $this->assertSession()->checkboxNotChecked('Allow other Conditions on blocks');
    $this->assertSession()->checkboxChecked('All conditions must pass');
    $this->assertSession()->checkboxNotChecked('Only one condition must pass');

    // Edit group configuration.
    $this->getSession()->getPage()->checkField('Allow other Conditions on blocks');
    $this->getSession()->getPage()->selectFieldOption('logic', 'or');
    $this->getSession()->getPage()->pressButton('Save');

    // Assert new group configuration.
    $this->drupalGet('admin/structure/block/block-visibility-group/' . $group->id());
    $this->assertSession()->checkboxChecked('Allow other Conditions on blocks');
    $this->assertSession()->checkboxNotChecked('All conditions must pass');
    $this->assertSession()->checkboxChecked('Only one condition must pass');
  }

  /**
   * Test all options for existing blocks when deleting a visibility group.
   */
  public function testBlockOptionsWhenDeleting() {
    $group = $this->createGroup();
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());

    // Delete the block visibility group but keep existing blocks.
    $this->drupalGet("admin/structure/block/block-visibility-group/{$group->id()}/delete");
    $this->getSession()->getPage()->selectFieldOption('Current blocks', 'UNSET-BLOCKS');
    $this->getSession()->getPage()->pressButton('Delete');
    $this->assertPageTextContains('Deleted Block Visibility Group: @label', ['@label' => $group->label()]);

    // Assert all options exist.
    $this->drupalGet('admin/structure/block/manage/' . $block->id());
    $tabs = $this->assertSession()->elementExists('css', "div[data-drupal-selector=\"edit-visibility-tabs\"]");
    $items = $tabs->findAll('css', 'summary');
    self::assertEquals('Condition Group', $items[0]->getText());
    $this->assertSession()->fieldValueEquals('Block Visibility Groups', '');

    $group = $this->createGroup();
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());

    // Delete the block visibility group and its block.
    $this->drupalGet("admin/structure/block/block-visibility-group/{$group->id()}/delete");
    $this->getSession()->getPage()->selectFieldOption('Current blocks', 'DELETE-BLOCKS');
    $this->getSession()->getPage()->pressButton('Delete');
    $this->assertPageTextContains('Deleted Block Visibility Group: @label', ['@label' => $group->label()]);

    // Assert block was deleted.
    $this->drupalGet('admin/structure/block/manage/' . $block->id());
    $this->assertSession()->statusCodeEquals(404);
  }

  /**
   * Test that visibility group is locked when adding from group page.
   */
  public function testVisibilityGroupLocked() {
    $group = $this->createGroup();
    $settings['label_display'] = 'visible';
    $settings['label'] = $this->randomMachineName();
    $block = $this->drupalPlaceBlock('system_powered_by_block', $settings);

    $this->drupalGet('admin/structure/block/block-visibility-group');

    $ul = $this->assertSession()->elementExists('css', 'ul.dropbutton');
    $ul->clickLink('Manage Blocks');

    $selected = $this->assertSession()->elementExists('css', '#block-admin-display-form option:selected');
    self::assertEquals($group->label(), $selected->getText());

    // Find the block's row and visit it's configuration page.
    $row = $this->assertSession()->elementExists('css', "tr[data-drupal-selector=\"edit-blocks-{$block->id()}\"]");
    $row->clickLink('Configure');

    $select = $this->assertSession()->elementExists('css', '#edit-visibility-condition-group-block-visibility-group');
    self::assertTrue($select->hasAttribute('disabled'));
  }

  /**
   * Test redirects to group layout page when coming from specific group page.
   */
  public function testGroupLayoutPageRedirect() {
    $group = $this->createGroup();
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());

    $this->drupalGet('admin/structure/block/block-visibility-group/' . $group->id());
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->addressEquals('admin/structure/block/block-visibility-group');

    $ul = $this->assertSession()->elementExists('css', 'ul.dropbutton');
    $ul->clickLink('Manage Blocks');

    // Find the block's row and visit it's configuration page.
    $row = $this->assertSession()->elementExists('css', "tr[data-drupal-selector=\"edit-blocks-{$block->id()}\"]");
    $row->clickLink('Configure');

    $selected = $this->assertSession()->elementExists('css', '#edit-visibility-condition-group-block-visibility-group option:selected');
    self::assertEquals($selected->getText(), $group->label());

    $this->getSession()->getPage()->pressButton('Save block');

    $this->assertSession()->addressEquals('admin/structure/block');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertPageTextContains('The block configuration has been saved.');
    $selected = Url::fromRoute(
      'block.admin_display_theme',
      ['theme' => $this->defaultTheme],
      ['query' => ['block_visibility_group' => $group->id()]]
    )->toString();
    $this->assertSession()->fieldValueEquals('Block Visibility Group', $selected);
  }

  /**
   * Test Other visibility settings don't exist when option is set in group.
   */
  public function testBlockVisibilityTabs() {
    $group = $this->createGroup();
    $block = $this->placeBlockInGroup('system_powered_by_block', $group->id());

    $this->drupalGet('admin/structure/block/manage/' . $block->id());

    $tabs = $this->assertSession()->elementExists('css', "div[data-drupal-selector=\"edit-visibility-tabs\"]");
    $items = $tabs->findAll('css', 'summary');
    self::assertCount(1, $items);
    self::assertEquals('Condition Group', $items[0]->getText());
    $item = $tabs->find('css', '.form-item-visibility-condition-group-block-visibility-group');
    $label = $item->find('css', 'label');
    self::assertEquals('Block Visibility Groups', $label->getText());
    $this->assertSession()->fieldValueEquals('Block Visibility Groups', $group->id());
  }

  /**
   * Find the condition row by id and assert values.
   *
   * @param string $condition_id
   *   The condition id.
   * @param array $expected
   *   The expected values.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The node element.
   */
  protected function assertConditionConfigInGroupUi(string $condition_id, array $expected) {
    $row = $this->assertSession()->elementExists('css', "tr[data-drupal-selector=\"edit-conditions-$condition_id\"]");
    $cells = $row->findAll('css', 'td');
    self::assertCount(3, $cells);
    self::assertEquals($expected['label'], $cells[0]->getText());
    self::assertEquals($expected['description'], $cells[1]->getText());
    $this->assertSession()->elementExists('css', 'ul.dropbutton', $row);

    return $row;
  }

}
