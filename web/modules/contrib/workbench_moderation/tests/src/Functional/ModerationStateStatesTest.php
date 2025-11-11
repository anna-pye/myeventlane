<?php

namespace Drupal\Tests\workbench_moderation\Functional;

/**
 * Tests moderation state config entity.
 *
 * @group workbench_moderation
 */
class ModerationStateStatesTest extends ModerationStateTestBase {

  /**
   * Tests route access/permissions.
   */
  public function testAccess() {
    $paths = [
      'admin/structure/workbench-moderation',
      'admin/structure/workbench-moderation/states',
      'admin/structure/workbench-moderation/states/add',
      'admin/structure/workbench-moderation/states/draft',
      'admin/structure/workbench-moderation/states/draft/delete',
    ];

    foreach ($paths as $path) {
      $this->drupalGet($path);
      // No access.
      $this->assertSession()->statusCodeEquals(403);
    }
    $this->drupalLogin($this->adminUser);
    foreach ($paths as $path) {
      $this->drupalGet($path);
      // User has access.
      $this->assertSession()->statusCodeEquals(200);
    }
  }

  /**
   * Tests administration of moderation state entity.
   */
  public function testStateAdministration() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/structure/workbench-moderation');
    $this->assertSession()->linkExists('Moderation states');
    $this->assertSession()->linkExists('Moderation state transitions');
    $this->clickLink('Moderation states');
    $this->assertSession()->linkExists('Add Moderation state');
    $this->assertSession()->pageTextContains('Draft');
    // Edit the draft.
    $this->clickLink('Edit', 1);
    $this->assertSession()->fieldValueEquals('label', 'Draft');
    $this->assertSession()->checkboxNotChecked('edit-published');
    $this->submitForm([
      'label' => 'Drafty',
    ], 'Save');
    $this->assertSession()->pageTextContains('Saved the Drafty Moderation state.');
    $this->drupalGet('admin/structure/workbench-moderation/states/draft');
    $this->assertSession()->fieldValueEquals('label', 'Drafty');
    $this->submitForm([
      'label' => 'Draft',
    ], 'Save');
    $this->assertSession()->pageTextContains('Saved the Draft Moderation state.');
    $this->clickLink('Add Moderation state');
    $this->submitForm([
      'label' => 'Expired',
      'id' => 'expired',
    ], 'Save');
    $this->assertSession()->pageTextContains('Created the Expired Moderation state.');
    $this->drupalGet('admin/structure/workbench-moderation/states/expired');
    $this->clickLink('Delete');
    $this->assertSession()->pageTextContains('Are you sure you want to delete Expired?');
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('Moderation state Expired deleted');
  }

}
