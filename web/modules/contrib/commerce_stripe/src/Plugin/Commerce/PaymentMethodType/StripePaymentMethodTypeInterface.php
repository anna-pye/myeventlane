<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;

/**
 * Defines the interface for stripe payment method types.
 */
interface StripePaymentMethodTypeInterface {

  /**
   * Checks whether the given payment can be captured.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment to capture.
   *
   * @return bool
   *   TRUE if the payment can be captured, FALSE otherwise.
   */
  public function canCapturePayment(PaymentInterface $payment): bool;

  /**
   * Update the payment method from the stripe payment method.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param \Stripe\PaymentMethod $stripe_payment_method
   *   The stripe payment method.
   */
  public function updatePaymentMethod(PaymentMethodInterface $payment_method, PaymentMethod $stripe_payment_method): void;

  /**
   * Update the payment method from the stripe payment method.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentInterface $payment
   *   The payment.
   * @param \Stripe\PaymentIntent $stripe_payment_intent
   *   The stripe payment intent.
   */
  public function updatePayment(PaymentInterface $payment, PaymentIntent $stripe_payment_intent): void;

  /**
   * Returns a key/value array of supported payment method logos.
   *
   * Some payment method types might only return one, e.g. us_bank_account,
   * while others, e.g. card, will return multiple.
   *
   * @return array
   *   The key/value pair(s) of logos.
   */
  public function getLogos(): array;

  /**
   * Allow modification of the intent attributes before creation of the intent.
   *
   * @param array $intent_attributes_array
   *   The intent attributes array.
   */
  public function onIntentCreateAttributes(array &$intent_attributes_array): void;

  /**
   * Whether the payment method type is reusable.
   *
   * @return bool
   *   Whether the payment method type is reusable or not.
   */
  public function isReusable(): bool;

}
