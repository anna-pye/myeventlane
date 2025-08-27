<?php

namespace Drupal\myeventlane_tickets\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @Block(
 *   id = "myeventlane_tickets_add_to_cart",
 *   admin_label = @Translation("MyEventLane: Tickets ATC")
 * )
 */
final class TicketsAddToCartBlock extends BlockBase {

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->routeMatch = $container->get('current_route_match');
    return $instance;
  }

  /** @var \Drupal\Core\Routing\RouteMatchInterface */
  private RouteMatchInterface $routeMatch;

  public function build(): array {
    $node = $this->routeMatch->getParameter('node');
    if (!$node || !$node->hasField('field_ticket_product') || $node->get('field_ticket_product')->isEmpty()) {
      // Be explicit. Helps during theming.
      return ['#markup' => 'No ticket product linked.'];
    }
    $product = $node->get('field_ticket_product')->entity;
    if (!$product) {
      return ['#markup' => 'Linked product not found.'];
    }

    // Render the product in the add_to_cart view mode via lazy builder.
    return [
      'product' => [
        '#lazy_builder' => [
          'commerce_product.lazy_builders:addToCartForm',
          [$product->id(), 'add_to_cart', \Drupal::languageManager()->getCurrentLanguage()->getId()],
        ],
        '#create_placeholder' => TRUE,
      ],
      '#cache' => [
        'contexts' => ['route', 'languages:language_interface'],
        'tags' => $product->getCacheTags(),
        'max-age' => 0,
      ],
    ];
  }
}
