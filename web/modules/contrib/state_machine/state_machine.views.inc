<?php

/**
 * @file
 * Provide Views data for the State Machine module.
 *
 * @ingroup views_module_handlers
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_field_views_data().
 *
 * Views integration for state fields.
 */
function state_machine_field_views_data(FieldStorageConfigInterface $field) {
  if (\Drupal::hasService('views.field_data_provider')) {
    $data = \Drupal::service('views.field_data_provider')->defaultFieldImplementation($field);
  }
  else {
    $deprecated_function = 'views_field_default_views_data';
    $data = $deprecated_function($field);
  }
  $field_name = $field->getName();
  foreach ($data as $table_name => $table_data) {
    $data[$table_name][$field_name . '_value']['filter']['id'] = 'state_machine_state';
  }

  return $data;
}
