<?php

namespace Drupal\Tests\block_visibility_groups\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\block_visibility_groups\Traits\BlockVisibilityGroupTrait;

/**
 * Test javascript dependent functionality.
 *
 * @group block_visibility_groups
 */
class BlockVisibilityGroupsUiTest extends WebDriverTestBase {

  use BlockVisibilityGroupTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['block', 'block_visibility_groups'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Create and login with user who can administer blocks.
    $this->drupalLogin($this->drupalCreateUser([
      'administer blocks',
      'administer block visibility groups',
    ]));
  }

  /**
   * Test add, edit, delete conditions on block layout page.
   */
  public function testBlockLayoutPageConditionCrud() {
    $group = $this->createGroup();
    $this->placeBlockInGroup('system_powered_by_block', $group->id(), [
      'region' => 'content',
    ]);

    $this->drupalGet('admin/structure/block');
    $this->getSession()->getPage()->selectFieldOption('edit-select', $group->label());

    // Add condition.
    $this->assertSession()->waitForElementVisible('css', '#edit-conditions-section summary');
    $conditions = $this->getSession()->getPage()->find('css', '#edit-conditions-section summary');
    $conditions->click();
    $this->assertSession()->waitForElementVisible('css', '#edit-conditions');

    $this->assertSession()->elementContains('css', '#edit-conditions', 'There are no conditions.');
    $this->getSession()->getPage()->clickLink('Add new condition');

    $this->assertSession()->waitForElementVisible('css', '#drupal-modal');
    $this->assertSession()->assertVisibleInViewport('css', '#drupal-modal');

    $this->getSession()->getPage()->clickLink('User Role');
    $buttonpane = $this->assertSession()->waitForElementVisible('css', '.ui-dialog-buttonpane');
    $this->getSession()->getPage()->checkField('Authenticated user');
    $button = $buttonpane->findButton('Add condition');
    $button->press();

    // Assert condition as added.
    $this->assertPageTextContains('The @condition condition has been added.', ['@condition' => 'User Role']);
    $this->assertSession()->elementContains('css', '#edit-conditions', 'The user is a member of Authenticated user');

    // Edit condition.
    $conditions_table = $this->assertSession()->elementExists('css', '#edit-conditions');
    $conditions_table->clickLink('Edit');

    $this->assertSession()->waitForElementVisible('css', '[role="dialog"]');
    $this->getSession()->getPage()->checkField('Anonymous user');
    $button = $buttonpane->findButton('Update condition');
    $button->press();

    $this->assertSession()->waitForText('The User Role condition has been updated.');
    // Assert condition as updated.
    $this->assertPageTextContains('The @condition condition has been updated.', ['@condition' => 'User Role']);
    $this->assertSession()->elementContains('css', '#edit-conditions', 'The user is a member of Anonymous user, Authenticated user');

    // Delete condition.
    $arrow = $this->assertSession()->elementExists('css', '.dropbutton-widget button', $conditions_table);
    // Make delete link visible.
    $arrow->click();
    $conditions_table->clickLink('Delete');
    $this->assertSession()->waitForElementVisible('css', '[role="dialog"]');

    $this->assertSession()->elementContains('css', '#drupal-modal', 'This action cannot be undone.');

    $button = $buttonpane->findButton('Delete');
    $button->press();
    $this->assertPageTextContains('The condition @condition has been removed.', ['@condition' => 'User Role']);
  }

  /**
   * Correct blocks shown depends on the blocks layout page dropdown.
   */
  public function testDropdownSelection() {
    $group = $this->createGroup();
    $block_1 = $this->placeBlockInGroup('system_powered_by_block', $group->id(), [
      'region' => 'content',
    ]);
    $block_2 = $this->drupalPlaceBlock('system_branding_block', [
      'region' => 'content',
    ]);

    $this->drupalGet('admin/structure/block');

    // Assert "All Blocks" values.
    $this->getSession()->getPage()->selectFieldOption('edit-select', '- All Blocks -');

    $this->assertSession()->elementExists('css', "tr[data-drupal-selector=\"edit-blocks-{$block_1->id()}\"]");
    $this->assertSession()->elementExists('css', "tr[data-drupal-selector=\"edit-blocks-{$block_2->id()}\"]");

    // Assert "Global Blocks" values.
    $this->getSession()->getPage()->selectFieldOption('edit-select', '- Global blocks -');

    $this->assertSession()->elementNotExists('css', "tr[data-drupal-selector=\"edit-blocks-{$block_1->id()}\"]");
    $this->assertSession()->elementExists('css', "tr[data-drupal-selector=\"edit-blocks-{$block_2->id()}\"]");

    // Assert "Specific Group" values.
    $this->getSession()->getPage()->selectFieldOption('edit-select', $group->label());

    $this->assertSession()->checkboxChecked('Show Global Blocks');
    $this->assertSession()->elementContains('css', "tr[data-drupal-selector=\"edit-blocks-{$block_1->id()}\"]", $group->label());
    $this->assertSession()->elementContains('css', "tr[data-drupal-selector=\"edit-blocks-{$block_2->id()}\"]", 'Global');

    // Filter out global blocks.
    $this->getSession()->getPage()->uncheckField('Show Global Blocks');

    $this->assertSession()->elementExists('css', "tr[data-drupal-selector=\"edit-blocks-{$block_1->id()}\"]");
    $this->assertSession()->elementNotExists('css', "tr[data-drupal-selector=\"edit-blocks-{$block_2->id()}\"]");
  }

}
