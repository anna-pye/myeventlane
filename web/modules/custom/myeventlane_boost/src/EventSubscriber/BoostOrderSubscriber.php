<?php

declare(strict_types=1);

namespace Drupal\myeventlane_boost\EventSubscriber;

use Drupal\myeventlane_boost\BoostManager;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\OrderItemComparisonFieldsEvent;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_checkout\Event\CheckoutCompleteEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;


final class BoostOrderSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly BoostManager $manager,
    private readonly LoggerInterface $logger,
    private readonly RequestStack $requestStack,
  ) {}

  public static function getSubscribedEvents(): array {
    return [
      // ✅ Only ORDER_PAID goes to onOrderPaid
      OrderEvents::ORDER_PAID => 'onOrderPaid',
      // ✅ Checkout completion goes to onCheckoutComplete
      CheckoutEvents::COMPLETION => 'onCheckoutComplete',
      // Cart hooks for attaching target event and preventing merges
      CartEvents::CART_ENTITY_ADD => 'onCartEntityAdd',
      CartEvents::ORDER_ITEM_COMPARISON_FIELDS => 'onComparisonFields',
    ];
  }

  public function onOrderPaid(OrderEvent $event): void {
    $order = $event->getOrder();
    if (!$order instanceof OrderInterface) {
      $this->logger->warning('ORDER_PAID fired without a valid order.');
      return;
    }
    $this->logger->notice('ORDER_PAID for order @id', ['@id' => $order->id()]);
    $this->applyBoostsFromOrder($order);
  }

  // 🔧 Hardened: accept only CheckoutCompleteEvent, but gracefully ignore anything else
  public function onCheckoutComplete($event): void {
    if (!$event instanceof CheckoutCompleteEvent) {
      // Defensive: some mis-wired subscription sent the wrong event type.
      $this->logger->warning('onCheckoutComplete received unexpected event type: @type', [
        '@type' => is_object($event) ? get_class($event) : gettype($event),
      ]);
      return;
    }
    $order = $event->getOrder();
    if (!$order instanceof OrderInterface) {
      return;
    }
    // Only act here if the order is already paid (on-site capture gateways).
    if (!$order->isPaid()) {
      return;
    }
    $this->logger->notice('Checkout complete for paid order @id', ['@id' => $order->id()]);
    $this->applyBoostsFromOrder($order);
  }

  public function onCartEntityAdd(CartEntityAddEvent $event): void {
    $item = $event->getOrderItem();
    if ($item->bundle() !== 'boost') {
      return;
    }
    if (!$item->hasField('field_target_event')) {
      $this->logger->warning('Boost order item missing field_target_event at add-to-cart (bundle misconfigured?).');
      return;
    }
    if (!$item->get('field_target_event')->target_id) {
      $nid = (int) ($this->requestStack->getCurrentRequest()?->query->get('event') ?? 0);
      if ($nid > 0) {
        $item->set('field_target_event', ['target_id' => $nid]);
        $this->logger->info('Attached target event @nid to boost order item (cart add).', ['@nid' => $nid]);
      }
    }
  }

  public function onComparisonFields(OrderItemComparisonFieldsEvent $event): void {
    $item = $event->getOrderItem();
    if ($item->bundle() !== 'boost') {
      return;
    }
    $fields = $event->getComparisonFields();
    $fields[] = 'field_target_event';
    $event->setComparisonFields($fields);
  }

  /** Apply/extend boost on all valid items in the order. */
  private function applyBoostsFromOrder(OrderInterface $order): void {
    foreach ($order->getItems() as $item) {
      if ($item->bundle() !== 'boost' || !$item->hasField('field_target_event')) {
        continue;
      }
      $target = $item->get('field_target_event')->entity;
      $variation = $item->getPurchasedEntity();
      if (!$target || !$variation) {
        continue;
      }
      $days = (int) ($variation->get('field_boost_days')->value ?? 0);
      $this->manager->applyBoost((int) $target->id(), $days > 0 ? $days : 7);
    }
  }
}
