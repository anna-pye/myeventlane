<?php

use Drupal\block_visibility_groups\Entity\BlockVisibilityGroup;
use Drupal\Core\Render\BubbleableMetadata;

/**
 * Implements hook_token_info().
 */
function block_visibility_groups_token_info() {
  $info = [];
  $info['tokens']['current-page']['block-visibility-groups-list'] = [
    'name' => t("Block visibility groups"),
    'description' => t("Lists the visibility groups that evaluate to true on the current page."),
  ];
  return $info;
}

/**
 * Implements hook_tokens().
 */
function block_visibility_groups_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  if ($type !== 'current-page') {
    return;
  }
  $replacements = [];
  foreach ($tokens as $name => $original) {
    switch ($name) {
      case 'block-visibility-groups-list':
        $groups_config = \Drupal::service('config.storage')->listAll('block_visibility_groups.block_visibility_group.');
        $groups = array_map(function($group) { return substr($group, 47); }, $groups_config);
        $evaluator = \Drupal::service('block_visibility_groups.group_evaluator');
        $active_groups = array_filter($groups, function($group_name) use ($evaluator) {
          $group = BlockVisibilityGroup::load($group_name);
          return $evaluator->evaluateGroup($group);
        });
        // TODO look into using token_render_array or flagging that it should
        // work with this when it lands (but do not token module a dependency):
        // https://www.drupal.org/project/token/issues/3115486
        $replacements[$original] = implode(',', $active_groups);
        break;
    }
  }
  return $replacements;
}
