<?php

namespace Drupal\myeventlane_tickets\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\node\NodeInterface;

class EventCapacityCalculator {

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
    private readonly Connection $db,
  ) {}

  public function loadEventFromVariation(ProductVariationInterface $variation): ?NodeInterface {
    $product = $variation->getProduct();
    if (!$product) {
      return NULL;
    }
    // Prefer product -> field_event backref if site adds it later.
    if ($product->hasField('field_event') && !$product->get('field_event')->isEmpty()) {
      $target = $product->get('field_event')->entity;
      if ($target instanceof NodeInterface && $target->bundle() === 'event') {
        return $target;
      }
    }
    // Fallback: Event references product via field_product_target (current model).
    $nids = $this->etm->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'event')
      ->condition('field_product_target', $product->id())
      ->range(0, 1)
      ->execute();
    return $nids ? $this->etm->getStorage('node')->load(reset($nids)) : NULL;
  }

  /**
   * Sum of quantities across Completed (and Fulfillment) orders for this event's product.
   * Adjust states if you capture/authorize differently.
   */
  public function getTotalSoldForEvent(NodeInterface $event): int {
    $pid = $event->get('field_product_target')->target_id ?? NULL;
    if (!$pid) {
      return 0;
    }

    $query = $this->db->select('commerce_order_item', 'oi');
    $query->addExpression('COALESCE(SUM(oi.quantity), 0)', 'qty');
    $query->join('commerce_order', 'o', 'oi.order_id = o.order_id');
    $query->join('commerce_product_variation_field_data', 'v', 'v.variation_id = oi.purchased_entity');
    $query->condition('o.state', ['completed', 'fulfillment'], 'IN');
    $query->condition('v.product_id', $pid);

    return (int) $query->execute()->fetchField();
  }

  /**
   * Remaining capacity: null when no cap, else cap - sold (never negative).
   */
  public function remainingCapacity(NodeInterface $event): ?int {
    if ($event->get('field_event_capacity')->isEmpty()) {
      return NULL;
    }
    $cap = (int) $event->get('field_event_capacity')->value;
    $sold = $this->getTotalSoldForEvent($event);
    return max(0, $cap - $sold);
  }
}
