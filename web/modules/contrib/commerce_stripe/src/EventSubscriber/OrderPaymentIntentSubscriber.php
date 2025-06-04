<?php

namespace Drupal\commerce_stripe\EventSubscriber;

use Drupal\commerce\Utility\Error;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderAssignEvent;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_price\MinorUnitsConverterInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_stripe\ErrorHelper;
use Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripeInterface;
use Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripePaymentElement;
use Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripePaymentElementInterface;
use Drupal\Core\DestructableInterface;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\SetupIntent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribes to order events to synchronize orders with their payment intents.
 *
 * Payment intents contain the amount which should be charged during a
 * transaction. When a payment intent is confirmed server or client side, that
 * amount is what is charged. To ensure a proper charge amount, we must update
 * the payment intent amount whenever an order is updated.
 */
class OrderPaymentIntentSubscriber implements EventSubscriberInterface, DestructableInterface {

  /**
   * The intent IDs that need updating.
   *
   * @var int[]
   */
  protected array $updateList = [];

  /**
   * The intent IDs that need canceling.
   *
   * @var int[]
   */
  protected array $cancelList = [];

  /**
   * Constructs a new OrderPaymentIntentSubscriber object.
   *
   * @param \Drupal\commerce_price\MinorUnitsConverterInterface $minorUnitsConverter
   *   The minor units converter.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected MinorUnitsConverterInterface $minorUnitsConverter,
    protected LoggerInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      OrderEvents::ORDER_PRESAVE => 'onOrderPreSave',
      OrderEvents::ORDER_UPDATE => 'onOrderUpdate',
      OrderEvents::ORDER_ASSIGN => ['onOrderAssign', -200],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    /** @var array $balance */
    foreach ($this->updateList as $intent_id => $balance) {
      try {
        $intent = $this->getIntent($intent_id);
        // Only update an intent amount with one of the
        // following statuses: requires_payment_method, requires_confirmation.
        if (($intent instanceof PaymentIntent) && in_array($intent->status, [
          PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD,
          PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
        ], TRUE)) {
          PaymentIntent::update($intent_id, [
            'amount' => $balance['amount'],
            'currency' => $balance['currency'],
          ]);
        }
      }
      catch (ApiErrorException $e) {
        ErrorHelper::handleException($e);
      }
    }
    foreach ($this->cancelList as $intent_id) {
      try {
        $intent = $this->getIntent($intent_id);
        $intent?->cancel();
      }
      catch (\Throwable $throwable) {
        Error::logException($this->logger, $throwable);
      }
    }
  }

  /**
   * Ensures the Stripe payment intent is up to date.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The event.
   */
  public function onOrderUpdate(OrderEvent $event): void {
    $order = $event->getOrder();

    $gateway = $order->get('payment_gateway');
    if ($gateway->isEmpty() || !$gateway->entity instanceof PaymentGatewayInterface) {
      return;
    }

    $plugin = $gateway->entity->getPlugin();
    if (
      !($plugin instanceof StripeInterface) &&
      !($plugin instanceof StripePaymentElementInterface)
    ) {
      return;
    }

    $intent_id = $order->getData('stripe_intent');
    if ($intent_id === NULL) {
      return;
    }

    if ($balance = $this->getChangedOrderBalance($order)) {
      $this->updateList[$intent_id] = [
        'amount' => $this->minorUnitsConverter->toMinorUnits($balance),
        'currency' => $balance->getCurrencyCode(),
      ];
    }
  }

  /**
   * On order presave.
   *
   * Ensures the Stripe payment intent is cancelled and a new one is created
   * if the payment method is changed.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The event.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function onOrderPreSave(OrderEvent $event): void {
    $order = $event->getOrder();

    $gateway = $order->get('payment_gateway');
    if ($gateway->isEmpty() || !$gateway->entity instanceof PaymentGatewayInterface) {
      return;
    }

    $plugin = $gateway->entity->getPlugin();
    if (
      !($plugin instanceof StripeInterface) &&
      !($plugin instanceof StripePaymentElementInterface)
    ) {
      return;
    }

    $intent_id = $order->getData('stripe_intent');
    if ($intent_id === NULL) {
      return;
    }

    $payment_method = $order->get('payment_method')->getString();
    $original_payment_method = $order->original->get('payment_method')->getString();
    if ($payment_method !== $original_payment_method) {
      $cancel = TRUE;
      if ($original_payment_method === '') {
        // This might be the creation of a new payment method.
        // Double-check the intent status.
        $intent = $this->getIntent($intent_id);
        if ($intent instanceof PaymentIntent && in_array($intent->status, [
          PaymentIntent::STATUS_SUCCEEDED,
          PaymentIntent::STATUS_PROCESSING,
          PaymentIntent::STATUS_REQUIRES_CAPTURE,
        ], TRUE)) {
          $cancel = FALSE;
        }
        elseif ($intent instanceof SetupIntent && in_array($intent->status, [
          SetupIntent::STATUS_SUCCEEDED,
          SetupIntent::STATUS_PROCESSING,
        ], TRUE)) {
          $cancel = FALSE;
        }
      }
      if ($cancel) {
        $this->cancelList[$intent_id] = $intent_id;
        $order->unsetData('stripe_intent');
      }
    }
  }

  /**
   * Gets the changed balance of the order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Drupal\commerce_price\Price|null
   *   Changed balance of the order.
   */
  protected function getChangedOrderBalance(OrderInterface $order): ?Price {
    $balance = $order->getBalance();
    $original_balance = isset($order->original) ? $order->original->getBalance() : NULL;

    // When the cart is empty, but the order refresh is triggered.
    if (!$balance && !$original_balance) {
      return NULL;
    }

    // The product has been added to an empty cart.
    if ($balance && !$original_balance) {
      return $balance;
    }

    // Do not update the payment intent when the last product is removed
    // from the cart, as it will be updated the next time the order is updated.
    if (!$balance && $original_balance) {
      return NULL;
    }

    // The currency has changed.
    if ($original_balance->getCurrencyCode() !== $balance->getCurrencyCode()) {
      return $balance;
    }

    // The balance of the order has changed.
    if (!$original_balance->equals($balance)) {
      return $balance;
    }

    return NULL;
  }

  /**
   * React to an order being assigned.
   *
   * @param \Drupal\commerce_order\Event\OrderAssignEvent $event
   *   The Order Assignment event.
   */
  public function onOrderAssign(OrderAssignEvent $event): void {
    try {
      $order = $event->getOrder();
      $payment_gateway = $order->get('payment_gateway')->entity;
      if ($payment_gateway instanceof PaymentGatewayInterface) {
        $plugin = $payment_gateway->getPlugin();
        if ($plugin instanceof StripePaymentElement) {
          /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
          $payment_method = $order->get('payment_method')->entity;
          if ($payment_method !== NULL) {
            $stripe_payment_method_id = $payment_method->getRemoteId();
            if (!empty($stripe_payment_method_id)) {
              $stripe_payment_method = PaymentMethod::retrieve($stripe_payment_method_id);
              $payment_details = ['stripe_payment_method' => $stripe_payment_method, 'commerce_order' => $order];
              $plugin->attachCustomerToStripePaymentMethod($payment_method, $payment_details);
            }
          }
        }
      }
    }
    catch (\Throwable $throwable) {
      Error::logException($this->logger, $throwable);
    }

  }

  /**
   * Get the intent.
   *
   * @param string $intent_id
   *   The intent id.
   *
   * @return \Stripe\PaymentIntent|\Stripe\SetupIntent|null
   *   The intent.
   */
  protected function getIntent(string $intent_id): PaymentIntent|SetupIntent|null {
    $intent = NULL;
    try {
      if (str_starts_with($intent_id, 'pi_')) {
        $intent = PaymentIntent::retrieve($intent_id);
      }
      elseif (str_starts_with($intent_id, 'seti_')) {
        $intent = SetupIntent::retrieve($intent_id);
      }
    }
    catch (\Throwable $throwable) {
      Error::logException($this->logger, $throwable);
    }
    return $intent;
  }

}
