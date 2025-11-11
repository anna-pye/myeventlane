<?php

namespace Drupal\commerce_stripe;

use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayInterface;
use Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripeInterface;
use Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripePaymentElementInterface;

/**
 * Provides a general helper for Stripe.
 */
final class StripeHelper {

  // Base url for Stripe connect.
  public const BASE_CONNECT_URL = 'https://stripe-connect.centarro.io/stripe-connect';

  /**
   * Determines whether the given payment gateway is a Stripe gateway.
   *
   * @param \Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\PaymentGatewayInterface $plugin
   *   The payment gateway plugin.
   *
   * @return bool
   *   Whether the given payment gateway is a Stripe gateway.
   */
  public static function isStripeGateway(PaymentGatewayInterface $plugin): bool {
    return ($plugin instanceof StripeInterface || $plugin instanceof StripePaymentElementInterface);
  }

}
