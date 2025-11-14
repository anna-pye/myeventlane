<?php

namespace Drupal\myeventlane_tickets\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\commerce_product\Entity\Product;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Route target that renders the ticket matrix form for a given product.
 */
final class TicketMatrixController extends ControllerBase {

  /**
   * Product page: /tickets/product/{commerce_product}
   */
  public function product(Product $commerce_product) {
    // Build our custom form for this product.
    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['mel-ticket-matrix-page']],
      'form' => $this->formBuilder()->getForm(
        '\Drupal\myeventlane_tickets\Form\TicketMatrixForm',
        $commerce_product
      ),
    ];

    return $build;
  }
}

