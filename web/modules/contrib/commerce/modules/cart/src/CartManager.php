<?php

namespace Drupal\commerce_cart;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_cart\Event\CartEmptyEvent;
use Drupal\commerce_cart\Event\CartEntityAddEvent;
use Drupal\commerce_cart\Event\CartEvents;
use Drupal\commerce_cart\Event\CartOrderItemAddEvent;
use Drupal\commerce_cart\Event\CartOrderItemRemoveEvent;
use Drupal\commerce_cart\Event\CartOrderItemUpdateEvent;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_price\Calculator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Default implementation of the cart manager.
 *
 * Fires its own events, different from the order entity events by being a
 * result of user interaction (add to cart form, cart view, etc).
 */
class CartManager implements CartManagerInterface {

  /**
   * The order item storage.
   *
   * @var \Drupal\commerce_order\OrderItemStorageInterface
   */
  protected $orderItemStorage;

  /**
   * Constructs a new CartManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_cart\OrderItemMatcherInterface $orderItemMatcher
   *   The order item matcher.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    protected OrderItemMatcherInterface $orderItemMatcher,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
    $this->orderItemStorage = $entity_type_manager->getStorage('commerce_order_item');
  }

  /**
   * {@inheritdoc}
   */
  public function emptyCart(OrderInterface $cart, $save_cart = TRUE) {
    $order_items = $cart->getItems();
    foreach ($order_items as $order_item) {
      $order_item->delete();
    }
    $cart->setItems([]);
    $cart->setAdjustments([]);

    $this->eventDispatcher->dispatch(new CartEmptyEvent($cart, $order_items), CartEvents::CART_EMPTY);
    $this->resetCheckoutFlow($cart);
    $this->resetCheckoutStep($cart);
    if ($save_cart) {
      $cart->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addEntity(OrderInterface $cart, PurchasableEntityInterface $entity, $quantity = '1', $combine = TRUE, $save_cart = TRUE) {
    $order_item = $this->createOrderItem($entity, $quantity);
    return $this->addOrderItem($cart, $order_item, $combine, $save_cart);
  }

  /**
   * {@inheritdoc}
   */
  public function createOrderItem(PurchasableEntityInterface $entity, $quantity = '1') {
    $order_item = $this->orderItemStorage->createFromPurchasableEntity($entity, [
      'quantity' => $quantity,
    ]);

    return $order_item;
  }

  /**
   * {@inheritdoc}
   */
  public function addOrderItem(OrderInterface $cart, OrderItemInterface $order_item, $combine = TRUE, $save_cart = TRUE) {
    $purchased_entity = $order_item->getPurchasedEntity();
    $quantity = $order_item->getQuantity();
    $matching_order_item = NULL;
    if ($combine) {
      $matching_order_item = $this->orderItemMatcher->match($order_item, $cart->getItems());
    }
    if ($matching_order_item) {
      $new_quantity = Calculator::add($matching_order_item->getQuantity(), $quantity);
      $matching_order_item->setQuantity($new_quantity);
      $matching_order_item->save();
      $saved_order_item = $matching_order_item;
    }
    else {
      $order_item->set('order_id', $cart->id());
      $order_item->save();
      $cart->addItem($order_item);
      $saved_order_item = $order_item;
    }

    if ($purchased_entity) {
      $event = new CartEntityAddEvent($cart, $purchased_entity, $quantity, $saved_order_item);
      $this->eventDispatcher->dispatch($event, CartEvents::CART_ENTITY_ADD);
    }
    $event = new CartOrderItemAddEvent($cart, $quantity, $saved_order_item);
    $this->eventDispatcher->dispatch($event, CartEvents::CART_ORDER_ITEM_ADD);
    $this->resetCheckoutFlow($cart);
    $this->resetCheckoutStep($cart);
    if ($save_cart) {
      $cart->save();
    }

    return $saved_order_item;
  }

  /**
   * {@inheritdoc}
   */
  public function updateOrderItem(OrderInterface $cart, OrderItemInterface $order_item, $save_cart = TRUE) {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $original_order_item */
    $original_order_item = $this->orderItemStorage->loadUnchanged($order_item->id());
    $order_item->save();
    $event = new CartOrderItemUpdateEvent($cart, $order_item, $original_order_item);
    $this->eventDispatcher->dispatch($event, CartEvents::CART_ORDER_ITEM_UPDATE);
    $this->resetCheckoutFlow($cart);
    $this->resetCheckoutStep($cart);
    if ($save_cart) {
      $cart->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function removeOrderItem(OrderInterface $cart, OrderItemInterface $order_item, $save_cart = TRUE) {
    $order_item->delete();
    $cart->removeItem($order_item);
    $this->eventDispatcher->dispatch(new CartOrderItemRemoveEvent($cart, $order_item), CartEvents::CART_ORDER_ITEM_REMOVE);

    // If this results in an empty cart call the emptyCart method for
    // consistency.
    if ($cart->get('order_items')->isEmpty()) {
      $this->emptyCart($cart, $save_cart);
      return;
    }

    $this->resetCheckoutFlow($cart);
    $this->resetCheckoutStep($cart);
    if ($save_cart) {
      $cart->save();
    }
  }

  /**
   * Resets the checkout flow.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $cart
   *   The cart order.
   */
  protected function resetCheckoutFlow(OrderInterface $cart) {
    if ($cart->hasField('checkout_flow')) {
      $cart->set('checkout_flow', '');
    }
  }

  /**
   * Resets the checkout step.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $cart
   *   The cart order.
   */
  protected function resetCheckoutStep(OrderInterface $cart) {
    if ($cart->hasField('checkout_step')) {
      $cart->set('checkout_step', '');
    }
  }

}
