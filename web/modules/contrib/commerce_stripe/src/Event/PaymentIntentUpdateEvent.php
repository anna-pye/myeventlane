<?php

namespace Drupal\commerce_stripe\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\Component\Utility\NestedArray;

/**
 * Defines the payment intent update event.
 *
 * This enables other modules to alter the intent metadata sent to Stripe.
 *
 * @see \Drupal\commerce_stripe\Event\StripeEvents
 */
class PaymentIntentUpdateEvent extends EventBase {

  /**
   * Constructs a new PaymentIntentCreateEvent object.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param array $metadata
   *   The intent metadata.
   * @param \Drupal\commerce_payment\Entity\PaymentInterface|null $payment
   *   The payment, NULL if not set.
   */
  public function __construct(
    protected OrderInterface $order,
    protected array $metadata = [],
    protected ?PaymentInterface $payment = NULL,
  ) {}

  /**
   * Gets the intent metadata.
   *
   * @return array
   *   The intent metadata.
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * Sets the intent metadata.
   *
   * @param array $metadata
   *   The intent metadata.
   *
   * @return $this
   */
  public function setMetadata(array $metadata): self {
    $this->metadata = $metadata;
    return $this;
  }

  /**
   * Adds or modifies specific intent metadata.
   *
   * @param array $metadata
   *   The intent metadata.
   *
   * @return $this
   */
  public function addMetadata(array $metadata): self {
    $this->metadata = NestedArray::mergeDeep($this->metadata, $metadata);
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
