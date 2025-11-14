<?php

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

$changed = [];

// Add storage
if (!FieldStorageConfig::loadByName('node', 'field_boost_reminder_sent')) {
  FieldStorageConfig::create([
    'field_name' => 'field_boost_reminder_sent',
    'entity_type' => 'node',
    'type' => 'boolean',
    'cardinality' => 1,
  ])->save();
  $changed[] = 'field_storage:field_boost_reminder_sent';
}

// Attach to 'event' content type
if (!FieldConfig::loadByName('node', 'event', 'field_boost_reminder_sent')) {
  FieldConfig::create([
    'field_name' => 'field_boost_reminder_sent',
    'entity_type' => 'node',
    'bundle' => 'event',
    'label' => 'Boost reminder sent',
    'required' => FALSE,
    'default_value' => [['value' => 0]],
    'settings' => [],
  ])->save();
  $changed[] = 'field_config:field_boost_reminder_sent';
}

print "Added: " . implode(', ', $changed) . PHP_EOL;