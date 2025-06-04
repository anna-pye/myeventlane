<?php

namespace Drupal\commerce_stripe;

use Drupal\commerce_price\Price;
use Stripe\PaymentIntent;

/**
 * Provides intent convenience functionality.
 */
class IntentHelper {

  /**
   * Determine whether the payment is captured or not.
   *
   * @param \Stripe\PaymentIntent $intent
   *   The Stripe payment intent.
   *
   * @return bool
   *   Whether the payment is captured or not.
   */
  public static function getCapture(PaymentIntent $intent): bool {
    $capture = FALSE;
    switch ($intent->capture_method) {
      case 'automatic':
      case 'automatic_async':
        $capture = TRUE;
        break;
    }
    return $capture;
  }

  /**
   * Return a payment intent's amount/currency as a price.
   *
   * @param \Stripe\PaymentIntent $payment_intent
   *   The payment intent.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The price.
   */
  public static function getPrice(PaymentIntent $payment_intent): ?Price {
    $price = NULL;
    try {
      /** @var \Drupal\commerce_price\MinorUnitsConverterInterface $minor_units_converter */
      $minor_units_converter = \Drupal::getContainer()?->get('commerce_price.minor_units_converter');
      $price = $minor_units_converter->fromMinorUnits($payment_intent->amount, strtoupper($payment_intent->currency));
    }
    catch (\Throwable) {

    }
    return $price;
  }

}
