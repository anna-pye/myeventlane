<?php

namespace Drupal\commerce_stripe\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;

/**
 * Defines the payment method create event.
 *
 * This enables other modules to alter the payment method creation.
 */
class PaymentMethodEvent extends EventBase {

  /**
   * Constructs a new TransactionDataEvent object.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $paymentMethod
   *   The payment method.
   * @param array $paymentDetails
   *   The data supplied to the payment method.
   */
  public function __construct(
    protected PaymentMethodInterface $paymentMethod,
    protected array $paymentDetails,
  ) {}

  /**
   * Gets the payment method.
   *
   * @return \Drupal\commerce_payment\Entity\PaymentMethodInterface
   *   The payment method.
   */
  public function getPaymentMethod(): PaymentMethodInterface {
    return $this->paymentMethod;
  }

  /**
   * Gets the payment details data.
   *
   * @return array
   *   The payment details data.
   */
  public function getPaymentDetails(): array {
    return $this->paymentDetails;
  }

}
