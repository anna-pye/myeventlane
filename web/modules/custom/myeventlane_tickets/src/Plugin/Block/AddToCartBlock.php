<?php

declare(strict_types=1);

namespace Drupal\myeventlane_tickets\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\NodeInterface;

/**
 * Provides a "Tickets (Add to cart)" block for Event pages.
 *
 * @Block(
 *   id = "myeventlane_tickets_add_to_cart",
 *   admin_label = @Translation("MyEventLane: Tickets (Add to cart)")
 * )
 */
final class AddToCartBlock extends BlockBase {

  public function build(): array {
    $route_match = \Drupal::service('current_route_match');
    $node = $this->extractEventFromRoute($route_match);
    if (!$node instanceof NodeInterface || $node->bundle() !== 'event') {
      return ['#markup' => ''];
    }
    if ($node->get('field_product_target')->isEmpty()) {
      return ['#markup' => ''];
    }

    $product = $node->get('field_product_target')->entity;
    if (!$product) {
      return ['#markup' => ''];
    }
    $variations = $product->getVariations();
    if (empty($variations)) {
      return ['#markup' => ''];
    }

    // Render via product view mode that uses "Add to cart" formatter.
    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('commerce_product');
    $build = $view_builder->view($product, 'add_to_cart');
    if (empty($build)) {
      $build = $view_builder->view($product, 'default');
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['mel-tickets']],
      'content' => $build,
      '#cache' => [
        'contexts' => ['route'],
        'tags' => array_merge($node->getCacheTags(), $product->getCacheTags()),
        'max-age' => 0,
      ],
    ];
  }

  private function extractEventFromRoute(RouteMatchInterface $route_match): ?NodeInterface {
    $param = $route_match->getParameter('node');
    if ($param instanceof NodeInterface) {
      return $param;
    }
    if (is_numeric($param)) {
      return \Drupal::entityTypeManager()->getStorage('node')->load((int) $param);
    }
    return null;
  }
}
