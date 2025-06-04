<?php

namespace Drupal\commerce_stripe_test\EventSubscriber;

use Drupal\commerce_stripe\EventSubscriber\OrderPaymentIntentSubscriber;
use Stripe\Exception\ApiErrorException as StripeError;
use Stripe\PaymentIntent;

/**
 * Decorated Order PaymentIntent Subscriber.
 */
class DecoratedOrderPaymentIntentSubscriber extends OrderPaymentIntentSubscriber {

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
      catch (StripeError $e) {
        throw $e;
      }
    }
  }

}
