<?php

declare(strict_types=1);

namespace Drupal\myeventlane_tickets\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

/**
 * Renders the tickets UI for an Event.
 */
final class AddToCartController extends ControllerBase {

  /**
   * Route style A:
   *   path: '/tickets/add/{event_id}'
   *   _controller: 'Drupal\myeventlane_tickets\Controller\AddToCartController::add'
   */
  public function add(int $event_id): array {
    $node = $this->entityTypeManager()->getStorage('node')->load($event_id);
    if (!$node instanceof NodeInterface) {
      return ['#markup' => $this->t('Event not found.')];
    }
    return $this->renderTickets($node);
  }

  /**
   * Route style B (entity param):
   *   path: '/event/{node}/tickets'
   *   options.parameters.node.type: entity:node
   *   _controller: 'Drupal\myeventlane_tickets\Controller\AddToCartController::build'
   */
  public function build(NodeInterface $node): array {
    return $this->renderTickets($node);
  }

  /**
   * Shared renderer used by both routes.
   */
  private function renderTickets(NodeInterface $node): array {
    if ($node->bundle() !== 'event') {
      return ['#markup' => $this->t('Not an event.')];
    }

    if ($node->get('field_product_target')->isEmpty()) {
      return ['#markup' => $this->t('No ticket product linked to this event.')];
    }

    $product = $node->get('field_product_target')->entity;
    if (!$product) {
      return ['#markup' => $this->t('Ticket product missing.')];
    }

    $variations = $product->getVariations();
    if (empty($variations)) {
      return ['#markup' => $this->t('No ticket types are available yet.')];
    }

    return [
      '#theme' => 'myeventlane_tickets_add_to_cart',
      '#event' => $node,
      '#product' => $product,
      '#variations' => $variations,
      '#attached' => ['library' => ['myeventlane_tickets/add_to_cart']],
    ];
  }
}
