<?php

/**
 * @file
 * Hooks provided by the Layout Builder Styles Conditions module.
 */

use Drupal\Core\Form\FormStateInterface;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter the list of condition plugins available for Layout Builder Styles.
 *
 * This hook allows modules to modify the list of condition plugins that are
 * presented in the Layout Builder Styles Conditions settings form. Modules
 * might want to remove certain conditions or add custom ones.
 *
 * @param array $conditions
 *   An array of condition plugin definitions, keyed by plugin ID.
 *
 * @see \Drupal\lb_styles_conditions\Form\SettingsForm::buildForm()
 */
function hook_lb_styles_conditions_available_conditions_alter(array &$conditions, FormStateInterface $form_state, ?string $form_id = NULL): void {
  $conditions_to_remove = [
    'language',
    'request_path',
    'response_status',
    'current_theme',
  ];
  $conditions = array_diff_key($conditions, array_flip($conditions_to_remove));
}

/**
 * @}
 */
