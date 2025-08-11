<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\profile\Entity\ProfileInterface;
use Stripe\PaymentIntent;

/**
 * Provides the interface for the Stripe payment gateway.
 */
interface StripeInterface extends OnsitePaymentGatewayInterface, SupportsAuthorizationsInterface, SupportsRefundsInterface {

  /**
   * Get the Stripe API Publishable key set for the payment gateway.
   *
   * @return string|null
   *   The Stripe API publishable key.
   */
  public function getPublishableKey(): ?string;

  /**
   * Create a payment intent for an order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param array $intent_attributes
   *   (optional) An array of intent attributes.
   * @param \Drupal\commerce_payment\Entity\PaymentInterface|null $payment
   *   (optional) The payment.
   *
   * @return \Stripe\PaymentIntent|null
   *   The payment intent.
   */
  public function createPaymentIntent(OrderInterface $order, array $intent_attributes = [], ?PaymentInterface $payment = NULL): ?PaymentIntent;

  /**
   * Extracts address from the given Profile and formats it for Stripe.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The customer profile.
   * @param string $type
   *   The address type ("billing"|"shipping").
   *
   * @return array|null
   *   The formatted address array or NULL.
   *   The output array may (or may not) contain either of the following keys,
   *   depending on the data available in the profile:
   *   - name: The full name of the customer.
   *   - address: The address array.
   */
  public function getFormattedAddress(ProfileInterface $profile, string $type = 'billing'): ?array;

}
