<?php

namespace Drupal\Tests\workbench_moderation\Functional;

/**
 * Tests permission access control around nodes.
 *
 * @group workbench_moderation
 */
class NodeAccessTest extends ModerationStateTestBase {

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
   * Verifies that a non-admin user can still access the appropriate pages.
   */
  public function testPageAccess() {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('node/add/moderated_content');

    // Create a node to test with.
    $this->submitForm([
      'title[0][value]' => 'moderated content',
    ], 'Save and Create New Draft');
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties([
        'title' => 'moderated content',
      ]);

    if (!$nodes) {
      $this->fail('Test node was not saved correctly.');
      return;
    }

    /** @var \Drupal\node\NodeInterface $node */
    $node = reset($nodes);

    $view_path = 'node/' . $node->id();
    $edit_path = 'node/' . $node->id() . '/edit';
    $latest_path = 'node/' . $node->id() . '/latest';
    $this->drupalGet($edit_path);

    // Publish the node.
    $this->submitForm([], 'Save and Request Review');
    $this->drupalGet($edit_path);
    $this->submitForm([], 'Save and Publish');

    // Ensure access works correctly for anonymous users.
    $this->drupalLogout();

    $this->drupalGet($edit_path);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet($latest_path);
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet($view_path);
    $this->assertSession()->statusCodeEquals(200);

    // Create a forward revision for the 'Latest revision' tab.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($edit_path);
    $this->submitForm([
      'title[0][value]' => 'moderated content revised',
    ], 'Save and Create New Draft');

    // Now make a new user and verify that the new user's access is correct.
    $user = $this->createUser([
      'use draft_draft transition',
      'use draft_needs_review transition',
      'use published_draft transition',
      'use needs_review_published transition',
      'view latest version',
      'view any unpublished content',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet($edit_path);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet($latest_path);
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet($view_path);
    $this->assertSession()->statusCodeEquals(200);

    // Now make another user, who should not be able to see forward revisions.
    $user = $this->createUser([
      'use draft_needs_review transition',
      'use published_draft transition',
      'use needs_review_published transition',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet($edit_path);
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet($latest_path);
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet($view_path);
    $this->assertSession()->statusCodeEquals(200);
  }

}
