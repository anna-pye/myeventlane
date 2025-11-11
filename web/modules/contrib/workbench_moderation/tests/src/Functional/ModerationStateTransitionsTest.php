<?php

namespace Drupal\Tests\workbench_moderation\Functional;

/**
 * Tests moderation state transition config entity.
 *
 * @group workbench_moderation
 */
class ModerationStateTransitionsTest extends ModerationStateTestBase {

  /**
   * Tests route access/permissions.
   */
  public function testAccess() {
    $paths = [
      'admin/structure/workbench-moderation/transitions',
      'admin/structure/workbench-moderation/transitions/add',
      'admin/structure/workbench-moderation/transitions/draft_needs_review',
      'admin/structure/workbench-moderation/transitions/draft_needs_review/delete',
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
   * Tests administration of moderation state transition entity.
   */
  public function testTransitionAdministration() {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('admin/structure/workbench-moderation');
    $this->clickLink('Moderation state transitions');
    $this->assertSession()->linkExists('Add Moderation state transition');
    $this->assertSession()->pageTextContains('Request Review');

    // Edit the Draft » Needs review.
    $this->drupalGet('admin/structure/workbench-moderation/transitions/draft_needs_review');
    $this->assertSession()->fieldValueEquals('label', 'Request Review');
    $this->assertSession()->fieldValueEquals('stateFrom', 'draft');
    $this->assertSession()->fieldValueEquals('stateTo', 'needs_review');
    $this->submitForm([
      'label' => 'Draft to Needs review',
    ], 'Save');
    $this->assertSession()->pageTextContains('Saved the Draft to Needs review Moderation state transition.');
    $this->drupalGet('admin/structure/workbench-moderation/transitions/draft_needs_review');
    $this->assertSession()->fieldValueEquals('label', 'Draft to Needs review');
    // Now set it back.
    $this->submitForm([
      'label' => 'Request Review',
    ], 'Save');
    $this->assertSession()->pageTextContains('Saved the Request Review Moderation state transition.');

    // Add a new state.
    $this->drupalGet('admin/structure/workbench-moderation/states/add');
    $this->submitForm([
      'label' => 'Expired',
      'id' => 'expired',
    ], 'Save');
    $this->assertSession()->pageTextContains('Created the Expired Moderation state.');

    // Add a new transition.
    $this->drupalGet('admin/structure/workbench-moderation/transitions');
    $this->clickLink('Add Moderation state transition');
    $this->submitForm([
      'label' => 'Published » Expired',
      'id' => 'published_expired',
      'stateFrom' => 'published',
      'stateTo' => 'expired',
    ], 'Save');
    $this->assertSession()->pageTextContains('Created the Published » Expired Moderation state transition.');

    // Delete the new transition.
    $this->drupalGet('admin/structure/workbench-moderation/transitions/published_expired');
    $this->clickLink('Delete');
    $this->assertSession()->pageTextContains('Are you sure you want to delete Published » Expired?');
    $this->submitForm([], 'Delete');
    $this->assertSession()->pageTextContains('Moderation transition Published » Expired deleted');
  }

}
