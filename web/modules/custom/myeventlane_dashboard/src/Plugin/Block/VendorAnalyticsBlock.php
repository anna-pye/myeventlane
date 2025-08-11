<?php

namespace Drupal\myeventlane_dashboard\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a Vendor Analytics Block.
 *
 * @Block(
 *   id = "vendor_analytics_block",
 *   admin_label = @Translation("Vendor Analytics Chart")
 * )
 */
class VendorAnalyticsBlock extends BlockBase {
  public function build() {
    $build = [];
    $build['#markup'] = '<div id="vendor-analytics-app"></div>';
    $build['#attached']['library'][] = 'myeventlane_dashboard/vendor_analytics';
    return $build;
  }
}
