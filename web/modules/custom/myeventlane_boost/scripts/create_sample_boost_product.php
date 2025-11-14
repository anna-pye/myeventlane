<?php

declare(strict_types=1);

use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductType;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_product\Entity\ProductVariationType;

// Ensure there's at least one store.
$store_storage = \Drupal::entityTypeManager()->getStorage('commerce_store');
$stores = $store_storage->loadMultiple();
if (!$stores) {
  throw new \RuntimeException("No Commerce Store found. Create one at /admin/commerce/config/store first.");
}
$store = reset($stores);

// Check types.
if (!ProductType::load('boost_upgrade')) {
  throw new \RuntimeException("Product type 'boost_upgrade' not found. Run the scaffold first.");
}
if (!ProductVariationType::load('boost_duration')) {
  throw new \RuntimeException("Variation type 'boost_duration' not found. Run the scaffold first.");
}

// Create product.
$ts = (string) \Drupal::time()->getRequestTime();
$title = 'Boost Upgrade (Sample ' . $ts . ')';

$product = Product::create([
  'type' => 'boost_upgrade',
  'title' => $title,
  'status' => 1,
]);
if (method_exists($product, 'setStores')) {
  $product->setStores([$store]);
} else {
  $product->set('stores', [$store]);
}
$product->save();

// Create variations.
$defs = [
  ['days' => 7,  'amount' => '9.00',  'sku' => 'BOOST-7D-'],
  ['days' => 14, 'amount' => '19.00', 'sku' => 'BOOST-14D-'],
  ['days' => 30, 'amount' => '29.00', 'sku' => 'BOOST-30D-'],
];

$variations = [];
foreach ($defs as $i => $def) {
  $sku = $def['sku'] . $ts . '-' . ($i + 1);

  $variation = ProductVariation::create([
    'type' => 'boost_duration',
    'sku' => $sku,
    'price' => new Price($def['amount'], 'AUD'),
    'status' => 1,
    'product_id' => $product->id(),
  ]);
  $variation->set('field_boost_days', (int) $def['days']);
  $variation->save();
  $variations[] = $variation;
}

// Link variations and save.
$product->set('variations', $variations);
$product->save();

// Output.
$url = \Drupal\Core\Url::fromRoute('entity.commerce_product.canonical', [
  'commerce_product' => $product->id(),
], ['absolute' => TRUE])->toString();

print "Sample Boost product created:\n";
print "  Product ID: " . $product->id() . "\n";
print "  Title     : " . $product->label() . "\n";
print "  URL       : " . $url . "\n";
print "  Variations:\n";
foreach ($variations as $v) {
  $days = (int) $v->get('field_boost_days')->value;
  $price = $v->getPrice()->__toString();
  print "    - SKU=" . $v->getSku() . " | days={$days} | price={$price}\n";
}
print "Done.\n";