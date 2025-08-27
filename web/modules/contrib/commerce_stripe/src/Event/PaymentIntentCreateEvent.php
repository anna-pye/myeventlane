<?php

namespace Drupal\commerce_stripe\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Component\Utility\NestedArray;

/**
 * Defines the payment intent event.
 *
 * This enables other modules to alter the intent attributes sent to Stripe.
 *
 * @see \Drupal\commerce_stripe\Event\StripeEvents
 */
class PaymentIntentCreateEvent extends EventBase {

  /**
   * Constructs a new PaymentIntentCreateEvent object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param array $intentAttributes
   *   The intent attributes.
   * @param \Drupal\commerce_payment\Entity\PaymentInterface|null $payment
   *   The payment, NULL if not set.
   */
  public function __construct(
    protected OrderInterface $order,
    protected array $intentAttributes = [],
    protected ?PaymentInterface $payment = NULL,
  ) {}

  /**
   * Gets the intent attributes.
   *
   * @return array
   *   The intent attributes.
   */
  public function getIntentAttributes(): array {
    return $this->intentAttributes;
  }

  /**
   * Sets the intent attributes.
   *
   * @param array $intent_attributes
   *   The intent attributes.
   *
   * @return $this
   */
  public function setIntentAttributes(array $intent_attributes): self {
    $this->intentAttributes = $intent_attributes;
    return $this;
  }

  /**
   * Adds or modifies specific intent attributes.
   *
   * @param array $intent_attributes
   *   The intent attributes.
   *
   * @return $this
   */
  public function addIntentAttributes(array $intent_attributes): self {
    $this->intentAttributes = NestedArray::mergeDeep($this->intentAttributes, $intent_attributes);
    return $this;
  }

  /**
   * Gets the payment object if available.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentInterface|null
   *   The payment object.
   */
  public function getPayment(): ?PaymentInterface {
    return $this->payment;
  }

  /**
   * Get the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  public function getOrder(): OrderInterface {
    return $this->order;
  }

}
