<?php

declare(strict_types=1);

namespace Drupal\myeventlane_commerce\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;

/**
 * Book page: renders the linked product in "add_to_cart" view mode.
 */
final class BookController extends ControllerBase {

  public function book(NodeInterface $node): array {
    // Only for Event nodes.
    if ($node->bundle() !== 'event') {
      throw $this->createNotFoundException();
    }

    $build = [
      '#theme' => 'myeventlane_event_book',  // your existing theme hook
      '#title' => $node->label(),
      '#event_date_text' => $node->get('field_start_date')->value ?? '',
      '#venue_text' => $node->get('field_venue')->value ?? '',
      '#hero_url' => '',
      '#matrix_form' => [],
      '#cache' => [
        'contexts' => ['route', 'user.roles', 'url.query_args'],
        'tags' => $node->getCacheTags(),
      ],
    ];

    // Optional hero image URL.
    if ($node->hasField('field_image') && !$node->get('field_image')->isEmpty()) {
      $file = $node->get('field_image')->entity;
      if ($file) {
        $build['#hero_url'] = $this->fileUrlGenerator()->generateAbsoluteString($file->getFileUri());
      }
    }

    // Pull first referenced product and render it in "add_to_cart" mode.
    if ($node->hasField('field_product_target') && !$node->get('field_product_target')->isEmpty()) {
      $product = $node->get('field_product_target')->entity;
      if ($product) {
        $build['#matrix_form'] = $this->entityTypeManager()
          ->getViewBuilder('commerce_product')
          ->view($product, 'add_to_cart');
      }
    }

    return $build;
  }
}
