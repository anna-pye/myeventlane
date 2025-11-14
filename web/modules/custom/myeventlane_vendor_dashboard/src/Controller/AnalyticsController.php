<?php

namespace Drupal\myeventlane_vendor_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

class AnalyticsController extends ControllerBase {
  public function view($node) {
    return new Response("Analytics coming soon for event $node.");
  }
}