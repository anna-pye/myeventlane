<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType\PaymentMethodTypeBase;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;

/**
 * Provides the base stripe payment method type.
 */
abstract class StripePaymentMethodTypeBase extends PaymentMethodTypeBase implements StripePaymentMethodTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function updatePaymentMethod(PaymentMethodInterface $payment_method, PaymentMethod $stripe_payment_method): void {

  }

  /**
   * {@inheritdoc}
   */
  public function updatePayment(PaymentInterface $payment, PaymentIntent $stripe_payment_intent): void {

  }

  /**
   * {@inheritdoc}
   */
  public function canCapturePayment(PaymentInterface $payment): bool {
    return $payment->getState()->getId() === 'authorization';
  }

  /**
   * {@inheritDoc}
   */
  public function onIntentCreateAttributes(array &$intent_attributes_array): void {

  }

  /**
   * {@inheritDoc}
   */
  public function isReusable(): bool {
    return TRUE;
  }

}
