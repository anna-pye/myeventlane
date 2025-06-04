<?php

/**
 * @file
 * Provide views data.
 */

/**
 * Implements hook_views_plugins_argument_validator_alter().
 */
function taxonomy_machine_name_views_plugins_argument_validator_alter(array &$plugins) {
  $plugins['taxonomy_term_machine_name'] = [
    'parent' => 'parent',
    'plugin_type' => 'argument_validator',
    'register_theme' => TRUE,
    'title' => t('Taxonomy term machine name'),
    'short_title' => '',
    'id' => 'taxonomy_term_machine_name',
    'entity_type' => 'taxonomy_term',
    'class' => 'Drupal\taxonomy_machine_name\Plugin\views\argument_validator\TermMachineName',
    'provider' => 'taxonomy_machine_name',
  ];
}

/**
 * Implements hook_views_data_alter().
 */
function taxonomy_machine_name_views_data_alter(array &$data) {
  $data["taxonomy_term_field_data"]["machine_name"]["filter"]["id"] = 'taxonomy_index_machine_name';
}
