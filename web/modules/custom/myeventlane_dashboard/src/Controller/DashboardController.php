<?php

namespace Drupal\myeventlane_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;

class DashboardController extends ControllerBase {
  public function view() {
    return [
      '#theme' => 'vendor_dashboard',
      '#attached' => [
        'library' => ['myeventlane_theme/vendor_dashboard'],
      ],
    ];
  }
}
