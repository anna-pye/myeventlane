<?php

namespace Drupal\Tests\block_visibility_groups\Traits;

use Drupal\block_visibility_groups\Entity\BlockVisibilityGroup;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Helper methods to deal with block visibility group testing.
 */
trait BlockVisibilityGroupTrait {

  /**
   * Short hand for assertSession's pageTextContains.
   *
   * @param string $text
   *   The text to search for in the page.
   * @param array $placeholders
   *   Any placeholder values.
   */
  protected function assertPageTextContains(string $text, array $placeholders = []) {
    $this->assertSession()->pageTextContains(new FormattableMarkup($text, $placeholders));
  }

  /**
   * Creates a block and place it inside a block_visibility_group.
   *
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $group_id
   *   The group id.
   * @param array $settings
   *   The settings for the block_visibility_group.
   *
   * @return \Drupal\block\BlockInterface
   *   The block element.
   */
  protected function placeBlockInGroup(string $plugin_id, string $group_id, array $settings = []) {
    $settings += [
      'label_display' => 'visible',
      'label' => $this->randomMachineName(),
    ];
    $settings['visibility']['condition_group']['block_visibility_group'] = $group_id;

    return $this->drupalPlaceBlock($plugin_id, $settings);
  }

  /**
   * Helper to create a block visibility group.
   *
   * @param array $configs
   *   An array of configuration.
   * @param array $settings
   *   The settings for the block_visibility_group.
   *
   * @return \Drupal\block_visibility_groups\BlockVisibilityGroupInterface
   *   The BlockVisibilityGroup instance.
   */
  protected function createGroup(array $configs = [], array $settings = []) {
    $settings += [
      'id' => 'test_group',
      'label' => $this->randomMachineName(),
    ];

    $group = BlockVisibilityGroup::create($settings);
    $group->save();

    if (empty($configs)) {
      return $group;
    }

    foreach ($configs as $config) {
      $group->addCondition($config);
    }
    $group->save();

    return $group;
  }

}
