<?php

namespace Drupal\Tests\workbench_moderation\Functional;

/**
 * Tests the moderation form, specifically on nodes.
 *
 * @group workbench_moderation
 */
class ModerationFormTest extends ModerationStateTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->drupalLogin($this->adminUser);
    $this->createContentTypeFromUi('Moderated content', 'moderated_content', TRUE, [
      'draft',
      'needs_review',
      'published',
    ], 'draft');
    $this->grantUserPermissionToCreateContentOfType($this->adminUser, 'moderated_content');
  }

  /**
   * Tests the moderation form that shows on the latest version page.
   *
   * The latest version page only shows if there is a forward revision. There
   * is only a forward revision if a draft revision is created on a node where
   * the default revision is not a published moderation state.
   *
   * @see \Drupal\workbench_moderation\EntityOperations
   * @see \Drupal\Tests\workbench_moderation\Functional\ModerationStateBlockTest::testCustomBlockModeration
   */
  public function testModerationForm() {
    $this->drupalGet('node/add/moderated_content');
    // Create new moderated content in draft.
    $this->submitForm([
      'title[0][value]' => 'Some moderated content',
      'body[0][value]' => 'First version of the content.',
    ], 'Save and Create New Draft');

    $node = $this->drupalGetNodeByTitle('Some moderated content');
    $canonical_path = sprintf('node/%d', $node->id());
    $edit_path = sprintf('node/%d/edit', $node->id());
    $latest_version_path = sprintf('node/%d/latest', $node->id());

    $this->assertTrue($this->adminUser->hasPermission('edit any moderated_content content'));

    // The canonical view should have a moderation form, because it is not the
    // live revision.
    $this->drupalGet($canonical_path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('edit-new-state');

    // The latest version page should not show, because there is no forward
    // revision.
    $this->drupalGet($latest_version_path);
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet($edit_path);

    // Update the draft.
    $this->submitForm([
      'body[0][value]' => 'Second version of the content.',
    ], 'Save and Request Review');

    // The canonical view should have a moderation form, because it is not the
    // live revision.
    $this->drupalGet($canonical_path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('edit-new-state');

    // The latest version page should not show, because there is still no
    // forward revision.
    $this->drupalGet($latest_version_path);
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet($edit_path);

    // Publish the draft.
    $this->submitForm([
      'body[0][value]' => 'Third version of the content.',
    ], 'Save and Publish');

    // The published view should not have a moderation form, because it is the
    // live revision.
    $this->drupalGet($canonical_path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldNotExists('edit-new-state');

    // The latest version page should not show, because there is still no
    // forward revision.
    $this->drupalGet($latest_version_path);
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet($edit_path);

    // Make a forward revision.
    $this->submitForm([
      'body[0][value]' => 'Fourth version of the content.',
    ], 'Save and Create New Draft');

    // The published view should not have a moderation form, because it is the
    // live revision.
    $this->drupalGet($canonical_path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldNotExists('edit-new-state');

    // The latest version page should show the moderation form and have "Draft"
    // status, because the forward revision is in "Draft".
    $this->drupalGet($latest_version_path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('edit-new-state');
    $this->assertSession()->pageTextContains('Draft');
    $this->drupalGet($latest_version_path);

    // Submit the moderation form to change status to needs review.
    $this->submitForm([
      'new_state' => 'needs_review',
    ], 'Apply');

    // The latest version page should show the moderation form and have "Needs
    // Review" status, because the forward revision is in "Needs Review".
    $this->drupalGet($latest_version_path);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldExists('edit-new-state');
    $this->assertSession()->pageTextContains('Needs Review');
  }

  /**
   * Tests the revision author is updated when the moderation form is used.
   */
  public function testModerationFormSetsRevisionAuthor() {
    // Create new moderated content in published.
    $node = $this->createNode(['type' => 'moderated_content', 'moderation_state' => 'published']);
    // Make a forward revision.
    $node->moderation_state->target_id = 'draft';
    $node->save();

    $another_user = $this->drupalCreateUser($this->permissions);
    $this->grantUserPermissionToCreateContentOfType($another_user, 'moderated_content');
    $this->drupalLogin($another_user);
    $this->drupalGet(sprintf('node/%d/latest', $node->id()));
    $this->submitForm([
      'new_state' => 'needs_review',
    ], 'Apply');

    $this->drupalGet(sprintf('node/%d/revisions', $node->id()));
    $this->assertSession()->pageTextContains('by ' . $another_user->getAccountName());
  }

}
