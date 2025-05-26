<?php

namespace Drupal\block_visibility_groups_admin\Plugin\ConditionCreator;

use Drupal\block_visibility_groups_admin\Plugin\ConditionCreatorBase;
use Drupal\user\Entity\Role;

/**
 * A condition creator to be used in creating user role condition.
 *
 * @ConditionCreator(
 *   id = "roles",
 *   label = "Roles",
 *   condition_plugin = "user_role"
 * )
 */
class RolesConditionCreator extends ConditionCreatorBase {

  /**
   * {@inheritdoc}
   */
  public function getNewConditionLabel() {
    return $this->t('Roles');
  }

  /**
   * {@inheritdoc}
   */
  public function createConditionElements() {
    $elements['condition_config'] = [
      '#tree' => TRUE,
    ];
    // @todo Dynamically create condition for by call ConditionPluginBase::buildConfigurationForm?
    // Backwards compatibility for the user_role_names() function being
    // deprecated in Drupal 10.2 and removed in D11.
    // @todo Drop this when the module requires 10.2.
    if (function_exists('user_role_names')) {
      // phpcs:ignore -- This function is deprecated in D10.2.
      $roles = user_role_names();
    }
    else {
      $roles = Role::loadMultiple();
    }
    $elements['condition_config']['roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('When the user has the following roles'),
      '#options' => array_map('\Drupal\Component\Utility\Html::escape', $roles),
      // '#description' => $this->t('If you select no roles, the condition will evaluate to TRUE for all users.'),
    ];
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function itemSelected($condition_info) {
    $roles = $condition_info['condition_config']['roles'];
    return !empty(array_filter($roles));
  }

  /**
   * {@inheritdoc}
   */
  public function createConditionConfig($plugin_info) {
    $config = parent::createConditionConfig($plugin_info);
    $config['roles'] = array_filter($config['roles']);
    // @todo Dynamically figure out context by loading connect plugin?
    $config['context_mapping'] = [
      'user' => '@user.current_user_context:current_user',
    ];
    return $config;
  }

}
