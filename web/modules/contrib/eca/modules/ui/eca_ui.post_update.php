<?php

/**
 * @file
 * Post update file for the ECA UI module.
 */

/**
 * Upgrade existing models to the v3 format.
 */
function eca_ui_post_update_migrate_to_v3(array &$sandbox): string {
  /** @var \Drupal\eca_ui\V3Migrate $migration */
  $migration = \Drupal::service('eca_ui.v3.migrate');
  // @phpstan-ignore-next-line
  $migration->migrateAll();
  return 'ECA V3 migration completed.';
}
