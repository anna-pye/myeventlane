<?php

use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\commerce_product\Entity\ProductType;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\commerce_order\Entity\OrderItemType;

$changed = [];

// Product type: boost_upgrade
if (!ProductType::load('boost_upgrade')) {
  ProductType::create([
    'id' => 'boost_upgrade',
    'label' => 'Boost Upgrade',
    'variationType' => 'boost_duration',
    'injectVariationFields' => TRUE,
  ])->save();
  $changed[] = 'product_type:boost_upgrade';
}

// Variation type: boost_duration (uses order item type "boost")
if (!ProductVariationType::load('boost_duration')) {
  ProductVariationType::create([
    'id' => 'boost_duration',
    'label' => 'Boost Duration',
    'orderItemType' => 'boost',
    'generateTitle' => TRUE,
  ])->save();
  $changed[] = 'variation_type:boost_duration';
}

// Order item type: boost
if (!OrderItemType::load('boost')) {
  OrderItemType::create([
    'id' => 'boost',
    'label' => 'Boost',
    'purchasableEntityType' => 'commerce_product_variation',
  ])->save();
  $changed[] = 'order_item_type:boost';
}

// Variation field: field_boost_days (int)
if (!FieldStorageConfig::loadByName('commerce_product_variation', 'field_boost_days')) {
  FieldStorageConfig::create([
    'field_name' => 'field_boost_days',
    'entity_type' => 'commerce_product_variation',
    'type' => 'integer',
    'cardinality' => 1,
  ])->save();
}
if (!FieldConfig::loadByName('commerce_product_variation', 'boost_duration', 'field_boost_days')) {
  FieldConfig::create([
    'field_name' => 'field_boost_days',
    'entity_type' => 'commerce_product_variation',
    'bundle' => 'boost_duration',
    'label' => 'Boost days',
    'required' => TRUE,
  ])->save();
  $changed[] = 'variation_field:field_boost_days';
}

// Order item field: field_target_event → node:event
if (!FieldStorageConfig::loadByName('commerce_order_item', 'field_target_event')) {
  FieldStorageConfig::create([
    'field_name' => 'field_target_event',
    'entity_type' => 'commerce_order_item',
    'type' => 'entity_reference',
    'settings' => ['target_type' => 'node'],
    'cardinality' => 1,
  ])->save();
}
if (!FieldConfig::loadByName('commerce_order_item', 'boost', 'field_target_event')) {
  FieldConfig::create([
    'field_name' => 'field_target_event',
    'entity_type' => 'commerce_order_item',
    'bundle' => 'boost',
    'label' => 'Target event',
    'required' => TRUE,
    'settings' => [
      'handler' => 'default',
      'handler_settings' => ['target_bundles' => ['event' => 'event']],
    ],
  ])->save();
  $changed[] = 'order_item_field:field_target_event';
}

// Event fields: field_promoted + field_promo_expires
if (!FieldStorageConfig::loadByName('node', 'field_promoted')) {
  FieldStorageConfig::create([
    'field_name' => 'field_promoted',
    'entity_type' => 'node',
    'type' => 'boolean',
  ])->save();
}
if (!FieldConfig::loadByName('node', 'event', 'field_promoted')) {
  FieldConfig::create([
    'field_name' => 'field_promoted',
    'entity_type' => 'node',
    'bundle' => 'event',
    'label' => 'Promoted',
  ])->save();
  $changed[] = 'event_field:field_promoted';
}

if (!FieldStorageConfig::loadByName('node', 'field_promo_expires')) {
  FieldStorageConfig::create([
    'field_name' => 'field_promo_expires',
    'entity_type' => 'node',
    'type' => 'datetime',
    'settings' => ['datetime_type' => 'datetime'],
  ])->save();
}
if (!FieldConfig::loadByName('node', 'event', 'field_promo_expires')) {
  FieldConfig::create([
    'field_name' => 'field_promo_expires',
    'entity_type' => 'node',
    'bundle' => 'event',
    'label' => 'Promotion expires',
  ])->save();
  $changed[] = 'event_field:field_promo_expires';
}

print "Scaffold done: " . implode(', ', $changed) . PHP_EOL;
