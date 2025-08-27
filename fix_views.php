<?php

/**
 * Candidate base tables for the broken views.
 * We will pick the first one that exists in your site\'s views data.
 */
$want = [
  // Commerce
  "commerce_cart_block" => ["commerce_order_item"],
  "commerce_cart_form" => ["commerce_order_item"],
  "commerce_carts" => ["commerce_order"],
  "commerce_checkout_order_summary" => ["commerce_order"],
  "commerce_order_item_table" => ["commerce_order_item"],
  "commerce_order_payments" => ["commerce_payment"],
  "commerce_orders" => ["commerce_order"],
  "commerce_products" => ["commerce_product_field_data","commerce_product"],
  "commerce_stores" => ["commerce_store","commerce_store_field_data"],
  "commerce_user_orders" => ["commerce_order"],

  // Core / content admin
  "content" => ["node_field_data","node"],
  "files" => ["file_managed"],
  "latest" => ["node_field_data","node"],
  "media" => ["media_field_data","media"],
  "media_library" => ["media_field_data","media"],
  "moderated_content" => ["content_moderation_state","content_moderation_state_field_data"],
  "profiles" => ["profile","users_field_data"],
  "publishing_content" => ["node_field_data","node"],
  "recent_content" => ["node_field_data","node"],
  "redirect" => ["redirect"],
  "taxonomy_term" => ["taxonomy_term_field_data","taxonomy_term_data"],
  "user_admin_people" => ["users_field_data","users"],
  "watchdog" => ["watchdog"],

  // Custom/other
  "recommended_for_you_affinity" => ["myeventlane_user_affinity","node_field_data"],
  "rsvp_submission" => ["node_field_data"], // fallback to nodes (filter by bundle in the view)
  "scheduler_scheduled_commerce_product" => ["commerce_product_field_data"],
  "scheduler_scheduled_content" => ["node_field_data"],
  "scheduler_scheduled_media" => ["media_field_data","media"],
  "scheduler_scheduled_taxonomy_term" => ["taxonomy_term_field_data","taxonomy_term_data"],
  "social_auth_profiles" => ["social_auth","users_field_data"], // prefer social_auth if present
  "trending_events" => ["node_field_data"],
  "vendor_ticket_sales" => ["commerce_order_item"],
];

// What base tables actually exist on this site:
$has = array_fill_keys(array_keys(\Drupal::service("views.views_data")->getAll()), TRUE);

// Load and repair.
$storage = \Drupal::entityTypeManager()->getStorage("view");
$views = $storage->loadMultiple();

$fixed = [];
$skipped = [];
foreach ($views as $id => $view) {
  if (!isset($want[$id])) { continue; }
  $conf = $view->toArray();
  $base = $conf["display"]["default"]["display_options"]["base_table"] ?? NULL;
  if (!empty($base)) { continue; } // already ok

  $chosen = NULL;
  foreach ($want[$id] as $candidate) {
    if (isset($has[$candidate])) { $chosen = $candidate; break; }
  }
  if (!$chosen) {
    $skipped[] = $id . " (no valid candidates: " . implode(", ", $want[$id]) . ")";
    continue;
  }

  $conf["display"]["default"]["display_options"]["base_table"] = $chosen;
  $view->set("display", $conf["display"]);
  try {
    $view->save();
    $fixed[] = "$id => $chosen";
  }
  catch (\Throwable $e) {
    $skipped[] = $id . " (save failed: " . $e->getMessage() . ")";
  }
}

echo "Fixed base_table on:\n";
foreach ($fixed as $f) { echo "  - $f\n"; }

if ($skipped) {
  echo "\nSkipped:\n";
  foreach ($skipped as $s) { echo "  - $s\n"; }
}

// Rebuild Views data + caches.
\Drupal::service("views.views_data")->clear();
