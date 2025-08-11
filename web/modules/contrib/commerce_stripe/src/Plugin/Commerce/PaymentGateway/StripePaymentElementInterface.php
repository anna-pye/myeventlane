<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsAuthorizationsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsRefundsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\profile\Entity\ProfileInterface;
use Stripe\PaymentIntent;
use Stripe\SetupIntent;

/**
 * Provides the interface for the Stripe Payment Element payment gateway.
 */
interface StripePaymentElementInterface extends OffsitePaymentGatewayInterface, SupportsAuthorizationsInterface, SupportsRefundsInterface, SupportsStoredPaymentMethodsInterface {

  /**
   * Get the Stripe API version of the payment gateway.
   *
   * @return string
   *   The Stripe API version.
   */
  public function getApiVersion(): string;

  /**
   * Get the Stripe API Publishable key set for the payment gateway.
   *
   * @return string
   *   The Stripe API publishable key.
   */
  public function getPublishableKey(): string;

  /**
   * Get the Stripe API Secret key set for the payment gateway.
   *
   * @return string
   *   The Stripe API secret key.
   */
  public function getSecretKey(): string;

  /**
   * Get the Stripe webhook signing secret.
   *
   * @return string
   *   The Stripe webhook signing secret.
   */
  public function getWebhookSigningSecret(): string;

  /**
   * Get the Stripe payment_method_usage key for the payment gateway.
   *
   * @return string
   *   The payment_method_usage key for the payment gateway.
   */
  public function getPaymentMethodUsage(): string;

  /**
   * Get the Stripe capture_method for the payment gateway.
   *
   * @return string|null
   *   The capture_method for the payment gateway.
   */
  public function getCaptureMethod(): ?string;

  /**
   * Get the Stripe checkout_form_display_label array for the payment gateway.
   *
   * @return array
   *   The checkout_form_display_label array for the payment gateway.
   */
  public function getCheckoutFormDisplayLabel(): array;

  /**
   * Create an intent for an order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return \Stripe\PaymentIntent|\Stripe\SetupIntent
   *   The intent.
   */
  public function createIntent(OrderInterface $order): PaymentIntent|SetupIntent;

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
   * @return \Stripe\PaymentIntent
   *   The payment intent.
   */
  public function createPaymentIntent(OrderInterface $order, array $intent_attributes = [], ?PaymentInterface $payment = NULL): PaymentIntent;

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

  /**
   * Get the intent.
   *
   * @param string|null $intent_id
   *   The intent id.
   *
   * @return \Stripe\PaymentIntent|\Stripe\SetupIntent|null
   *   The intent.
   *
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function getIntent(?string $intent_id): PaymentIntent|SetupIntent|null;

}
