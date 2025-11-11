<?php

namespace Drupal\Tests\workbench_moderation\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\workbench_moderation\Entity\ModerationState;

/**
 * Tests ModerationState.
 *
 * @coversDefaultClass \Drupal\workbench_moderation\Entity\ModerationState
 *
 * @group workbench_moderation
 */
class ModerationStateEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['workbench_moderation'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('moderation_state');
  }

  /**
   * Verify moderation state methods based on entity properties.
   *
   * @covers ::isPublishedState
   * @covers ::isDefaultRevisionState
   *
   * @dataProvider moderationStateProvider
   */
  public function testModerationStateProperties($published, $default_revision, $is_published, $is_default) {
    $moderation_state_id = $this->randomMachineName();
    $moderation_state = ModerationState::create([
      'id' => $moderation_state_id,
      'label' => $this->randomString(),
      'published' => $published,
      'default_revision' => $default_revision,
    ]);
    $moderation_state->save();

    $moderation_state = ModerationState::load($moderation_state_id);
    $this->assertEquals($is_published, $moderation_state->isPublishedState());
    $this->assertEquals($is_default, $moderation_state->isDefaultRevisionState());
  }

  /**
   * Data provider for ::testModerationStateProperties.
   */
  public static function moderationStateProvider() {
    return [
      // Draft, Needs review; should not touch the default revision.
      [FALSE, FALSE, FALSE, FALSE],
      // Published; this state should update and publish the default revision.
      [TRUE, TRUE, TRUE, TRUE],
      // Archive; this state should update but not publish the default revision.
      [FALSE, TRUE, FALSE, TRUE],
      // We try to prevent creating this state via the UI, but when a moderation
      // state is a published state, it should also become the default revision.
      [TRUE, FALSE, TRUE, TRUE],
    ];
  }

}
