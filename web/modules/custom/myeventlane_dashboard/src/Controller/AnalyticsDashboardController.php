<?php

namespace Drupal\myeventlane_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;

class AnalyticsDashboardController extends ControllerBase {
  public function view() {
    return [
      '#theme' => 'page__dashboard__analytics',
      '#attached' => [
        'library' => ['myeventlane_theme/vendor_analytics_chart'],
      ],
    ];
  }
}
