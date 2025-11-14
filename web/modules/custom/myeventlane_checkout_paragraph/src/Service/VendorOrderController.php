<?php

namespace Drupal\myeventlane_checkout_paragraph\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class VendorOrderController extends ControllerBase {

  protected $attendeeRenderer;

  public function __construct($attendeeRenderer) {
    $this->attendeeRenderer = $attendeeRenderer;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('myeventlane_checkout_paragraph.attendee_renderer')
    );
  }

  public function attendees(OrderInterface $commerce_order) {
    $build = [
      '#theme' => 'table',
      '#header' => ['First Name', 'Last Name', 'Email'],
      '#rows' => $this->attendeeRenderer->renderForOrder($commerce_order),
      '#cache' => ['max-age' => 0],
    ];

    return $build;
  }

}