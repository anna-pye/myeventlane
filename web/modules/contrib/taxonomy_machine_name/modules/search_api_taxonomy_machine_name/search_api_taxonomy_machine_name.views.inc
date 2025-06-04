<?php

/**
 * @file
 * Provide views data.
 */

use Drupal\search_api\Entity\Index;

/**
 * Implements hook_views_data_alter().
 */
function search_api_taxonomy_machine_name_views_data_alter(array &$data) {
  // Adjust the filter handler for all taxonomy machine name fields.
  foreach (Index::loadMultiple() as $index) {
    $key = 'search_api_index_' . $index->id();
    if (!isset($data[$key])) {
      continue;
    }

    foreach ($data[$key] as $field_alias => &$field_definition) {
      if (!isset($field_definition['field']['entity_type'], $field_definition['field']['field_name'])) {
        continue;
      }

      if ($field_definition['field']['entity_type'] !== 'taxonomy_term' || $field_definition['field']['field_name'] !== 'machine_name') {
        continue;
      }

      $field_definition['filter']['id'] = 'search_api_taxonomy_machine_name';
    }
  }
}
