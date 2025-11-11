<?php

namespace Drupal\Tests\workbench_moderation\Functional;

/**
 * Tests moderation state node type integration.
 *
 * @group workbench_moderation
 */
class ModerationStateNodeTypeTest extends ModerationStateTestBase {

  /**
   * A node type without moderation state disabled.
   */
  public function testNotModerated() {
    $this->drupalLogin($this->adminUser);
    $this->createContentTypeFromUi('Not moderated', 'not_moderated');
    $this->assertSession()->pageTextContains('The content type Not moderated has been added.');
    $this->grantUserPermissionToCreateContentOfType($this->adminUser, 'not_moderated');
    $this->drupalGet('node/add/not_moderated');
    $this->assertSession()->responseContains('Save as unpublished');
    $this->submitForm([
      'title[0][value]' => 'Test',
    ], 'Save and publish');
    $this->assertSession()->pageTextContains('Not moderated Test has been created.');
  }

  /**
   * Tests enabling moderation on an existing node-type, with content.
   */

  /**
   * A node type without moderation state enabled.
   */
  public function testEnablingOnExistingContent() {

    // Create a node type that is not moderated.
    $this->drupalLogin($this->adminUser);
    $this->createContentTypeFromUi('Not moderated', 'not_moderated');
    $this->grantUserPermissionToCreateContentOfType($this->adminUser, 'not_moderated');

    // Create content.
    $this->drupalGet('node/add/not_moderated');
    $this->submitForm([
      'title[0][value]' => 'Test',
    ], 'Save and publish');
    $this->assertSession()->pageTextContains('Not moderated Test has been created.');

    // Now enable moderation state.
    $this->enableModerationThroughUi('not_moderated',
    ['draft', 'needs_review', 'published'], 'draft');

    // And make sure it works.
    $nodes = \Drupal::entityTypeManager()->getStorage('node')->loadByProperties([
      'title' => 'Test',
    ]);
    if (empty($nodes)) {
      $this->fail('Could not load node with title Test');
      return;
    }
    $node = reset($nodes);
    $this->drupalGet('node/' . $node->id());
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkByHrefExists('node/' . $node->id() . '/edit');
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->responseContains('Save and Create New Draft');
    $this->assertSession()->responseNotContains('Save and publish');
  }

}
