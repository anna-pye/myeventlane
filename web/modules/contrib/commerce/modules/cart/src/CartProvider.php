<?php

namespace Drupal\commerce_cart;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_cart\Exception\DuplicateCartException;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\commerce_store\Entity\StoreInterface;

/**
 * Default implementation of the cart provider.
 */
class CartProvider implements CartProviderInterface {

  /**
   * The order storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $orderStorage;

  /**
   * The loaded cart data, grouped by uid, then keyed by cart order ID.
   *
   * Each data item is an array with the following keys:
   * - type: The order type.
   * - store_id: The store ID.
   *
   * @var array
   */
  protected $cartData = [];

  /**
   * Constructs a new CartProvider object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_store\CurrentStoreInterface $currentStore
   *   The current store.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\commerce_cart\CartSessionInterface $cartSession
   *   The cart session.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    protected CurrentStoreInterface $currentStore,
    protected AccountInterface $currentUser,
    protected CartSessionInterface $cartSession,
  ) {
    $this->orderStorage = $entity_type_manager->getStorage('commerce_order');
  }

  /**
   * {@inheritdoc}
   */
  public function createCart($order_type, ?StoreInterface $store = NULL, ?AccountInterface $account = NULL) {
    $store = $store ?: $this->currentStore->getStore();
    $account = $account ?: $this->currentUser;
    $uid = $account->id();
    $store_id = $store->id();
    if ($this->getCartId($order_type, $store, $account)) {
      // Don't allow multiple cart orders matching the same criteria.
      throw new DuplicateCartException("A cart order for type '$order_type', store '$store_id' and account '$uid' already exists.");
    }

    // Create the new cart order.
    $cart = $this->orderStorage->create([
      'type' => $order_type,
      'store_id' => $store_id,
      'uid' => $uid,
      'cart' => TRUE,
    ]);
    $cart->save();
    // Store the new cart order id in the anonymous user's session so that it
    // can be retrieved on the next page load.
    if ($account->isAnonymous()) {
      $this->cartSession->addCartId($cart->id());
    }
    // Cart data has already been loaded, add the new cart order to the list.
    if (isset($this->cartData[$uid])) {
      $this->cartData[$uid][$cart->id()] = [
        'type' => $order_type,
        'store_id' => $store_id,
      ];
    }

    return $cart;
  }

  /**
   * {@inheritdoc}
   */
  public function finalizeCart(OrderInterface $cart, $save_cart = TRUE) {
    $cart->cart = FALSE;
    if ($save_cart) {
      $cart->save();
    }
    // The cart is anonymous, move it to the 'completed' session.
    if (!$cart->getCustomerId()) {
      $this->cartSession->deleteCartId($cart->id(), CartSession::ACTIVE);
      $this->cartSession->addCartId($cart->id(), CartSession::COMPLETED);
    }
    // Remove the cart order from the internal cache, if present.
    if (isset($this->cartData[$cart->getCustomerId()][$cart->id()])) {
      unset($this->cartData[$cart->getCustomerId()][$cart->id()]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCart($order_type, ?StoreInterface $store = NULL, ?AccountInterface $account = NULL) {
    $cart = NULL;
    $cart_id = $this->getCartId($order_type, $store, $account);
    if ($cart_id) {
      $cart = $this->orderStorage->load($cart_id);
    }

    return $cart;
  }

  /**
   * {@inheritdoc}
   */
  public function getCartId($order_type, ?StoreInterface $store = NULL, ?AccountInterface $account = NULL) {
    $cart_id = NULL;
    $cart_data = $this->loadCartData($account);
    if ($cart_data) {
      $store = $store ?: $this->currentStore->getStore();
      $search = [
        'type' => $order_type,
        'store_id' => $store->id(),
      ];
      $cart_id = array_search($search, $cart_data);
    }

    return $cart_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getCarts(?AccountInterface $account = NULL, ?StoreInterface $store = NULL) {
    $carts = [];
    $cart_ids = $this->getCartIds($account, $store);
    if ($cart_ids) {
      $carts = $this->orderStorage->loadMultiple($cart_ids);
    }

    return $carts;
  }

  /**
   * {@inheritdoc}
   */
  public function getCartIds(?AccountInterface $account = NULL, ?StoreInterface $store = NULL) {
    // Filter out cart IDS that do not belong to the store passed.
    $cart_data = array_filter($this->loadCartData($account), function ($data) use ($store) {
      return !$store || $store->id() === $data['store_id'];
    });

    return array_keys($cart_data);
  }

  /**
   * {@inheritdoc}
   */
  public function clearCaches() {
    $this->cartData = [];
  }

  /**
   * Loads the cart data for the given user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user. If empty, the current user is assumed.
   *
   * @return array
   *   The cart data.
   */
  protected function loadCartData(?AccountInterface $account = NULL) {
    $account = $account ?: $this->currentUser;
    $uid = $account->id();
    if (isset($this->cartData[$uid])) {
      return $this->cartData[$uid];
    }

    if ($account->isAuthenticated()) {
      $query = $this->orderStorage->getQuery()
        ->condition('state', 'draft')
        ->condition('cart', TRUE)
        ->condition('uid', $account->id())
        ->sort('order_id', 'DESC')
        ->addTag('commerce_cart_order_ids')
        ->accessCheck(FALSE);
      $cart_ids = $query->execute();
    }
    else {
      $cart_ids = $this->cartSession->getCartIds();
    }

    $this->cartData[$uid] = [];
    if (!$cart_ids) {
      return [];
    }
    // Getting the cart data and validating the cart IDs received from the
    // session requires loading the entities. This is a performance hit, but
    // it's assumed that these entities would be loaded at one point anyway.
    /** @var \Drupal\commerce_order\Entity\OrderInterface[] $carts */
    $carts = $this->orderStorage->loadMultiple($cart_ids);
    $non_eligible_cart_ids = [];
    foreach ($carts as $cart) {
      if ($cart->isLocked()) {
        // Skip locked carts, the customer is probably off-site for payment.
        continue;
      }

      // Skip carts that are no longer eligible.
      if (!$this->isEligibleCart($cart, $account)) {
        $non_eligible_cart_ids[] = $cart->id();
        continue;
      }

      $this->cartData[$uid][$cart->id()] = [
        'type' => $cart->bundle(),
        'store_id' => $cart->getStoreId(),
      ];
    }
    // Avoid loading non-eligible carts on the next page load.
    if (!$account->isAuthenticated()) {
      foreach ($non_eligible_cart_ids as $cart_id) {
        $this->cartSession->deleteCartId($cart_id);
      }
    }

    return $this->cartData[$uid];
  }

  /**
   * Returns whether the given cart is "eligible" for the given user.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $cart
   *   The cart order.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   *
   * @return bool
   *   Whether the given cart is "eligible" for the given user.
   */
  protected function isEligibleCart(OrderInterface $cart, AccountInterface $account) : bool {
    // Carts that don't match customer ids should not be valid.
    if ($cart->getCustomerId() != $account->id()) {
      return FALSE;
    }
    // Empty carts should not be valid.
    if (empty($cart->cart->value)) {
      return FALSE;
    }
    // Carts not in draft mode should not be valid.
    if ($cart->getState()->getId() != 'draft') {
      return FALSE;
    }

    return TRUE;
  }

}
