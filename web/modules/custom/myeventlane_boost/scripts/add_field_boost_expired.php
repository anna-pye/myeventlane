<?php

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;

$changed = [];

if (!FieldStorageConfig::loadByName('node', 'field_boost_expired')) {
  FieldStorageConfig::create([
    'field_name' => 'field_boost_expired',
    'entity_type' => 'node',
    'type' => 'boolean',
    'cardinality' => 1,
  ])->save();
  $changed[] = 'field_storage:field_boost_expired';
}

if (!FieldConfig::loadByName('node', 'event', 'field_boost_expired')) {
  FieldConfig::create([
    'field_name' => 'field_boost_expired',
    'entity_type' => 'node',
    'bundle' => 'event',
    'label' => 'Boost expired',
    'default_value' => [['value' => 0]],
  ])->save();
  $changed[] = 'field_config:field_boost_expired';
}

print "Added: " . implode(', ', $changed) . PHP_EOL;