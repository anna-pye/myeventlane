<?php

namespace Drupal\myeventlane_recommend\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\myeventlane_recommend\Service\AffinityManager;
use Drupal\node\NodeInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Bumps affinity when an order is placed/paid for event tickets.
 */
final class OrderPlacedSubscriber implements EventSubscriberInterface {

  public function __construct(
    private AffinityManager $affinity,
    private EntityTypeManagerInterface $etm,
  ) {}

  /**
   * Subscribe to both the state machine 'placed' transition and 'paid' event.
   *
   * 'commerce_order.place.post_transition' -> when checkout completes.
   * OrderEvents::ORDER_PAID              -> when the order becomes fully paid.
   */
  public static function getSubscribedEvents(): array {
    return [
      'commerce_order.place.post_transition' => 'onOrderPlacedTransition',
      OrderEvents::ORDER_PAID => 'onOrderPaid',
    ];
  }

  /**
   * Handler for the place post transition (state machine).
   */
  public function onOrderPlacedTransition(WorkflowTransitionEvent $event): void {
    $entity = $event->getEntity();
    if ($entity instanceof OrderInterface) {
      $this->bumpForOrder($entity, /*delta=*/2.0);
    }
  }

  /**
   * Handler for the Commerce ORDER_PAID event.
   */
  public function onOrderPaid(OrderEvent $event): void {
    $order = $event->getOrder();
    $this->bumpForOrder($order, /*delta=*/2.0);
  }

  /**
   * Collect event topic term IDs from products in the order and bump affinity.
   */
  private function bumpForOrder(OrderInterface $order, float $delta): void {
    $uid = (int) $order->getCustomerId();
    if ($uid <= 0) {
      return;
    }

    $tids = [];

    // Walk order items -> purchased entities -> resolve Event node(s).
    foreach ($order->getItems() as $item) {
      $purchased = $item->getPurchasedEntity();
      if (!$purchased) {
        continue;
      }
      $product = method_exists($purchased, 'getProduct') ? $purchased->getProduct() : NULL;

      $event_node = $this->resolveEventFromProductOrVariation($product, $purchased)
        ?: ($product ? $this->resolveEventByReverseLookup((int) $product->id()) : NULL);

      if ($event_node instanceof NodeInterface && $event_node->hasField('field_topics') && !$event_node->get('field_topics')->isEmpty()) {
        foreach ($event_node->get('field_topics')->referencedEntities() as $term) {
          $tids[] = (int) $term->id();
        }
      }
    }

    if ($tids) {
      $this->affinity->bump($uid, array_unique($tids), $delta);
    }
  }

  /**
   * Try to read an Event node directly from common fields.
   */
  private function resolveEventFromProductOrVariation($product, $variation): ?NodeInterface {
    $candidates = ['field_event', 'field_event_target', 'field_event_ref'];

    // Check variation first.
    foreach ($candidates as $field) {
      if ($variation && $variation->hasField($field) && !$variation->get($field)->isEmpty()) {
        $entity = $variation->get($field)->entity;
        if ($entity instanceof NodeInterface) {
          return $entity;
        }
      }
    }
    // Then product.
    foreach ($candidates as $field) {
      if ($product && $product->hasField($field) && !$product->get($field)->isEmpty()) {
        $entity = $product->get($field)->entity;
        if ($entity instanceof NodeInterface) {
          return $entity;
        }
      }
    }
    return NULL;
  }

  /**
   * Fallback: event.node.field_product_target -> product id.
   */
  private function resolveEventByReverseLookup(int $product_id): ?NodeInterface {
    $storage = $this->etm->getStorage('node');
    $ids = $storage->getQuery()
      ->condition('type', 'event')
      ->condition('field_product_target', $product_id)
      ->range(0, 1)
      ->accessCheck(FALSE)
      ->execute();

    if ($ids) {
      $node = $storage->load(reset($ids));
      return $node instanceof NodeInterface ? $node : NULL;
    }
    return NULL;
  }
}
