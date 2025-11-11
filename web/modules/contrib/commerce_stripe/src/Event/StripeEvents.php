<?php

namespace Drupal\commerce_stripe\Event;

/**
 * Defines events for the Commerce Stripe module.
 */
class StripeEvents {

  /**
   * Name of the event fired before the Stripe PaymentIntent is created.
   *
   * This allows subscribers to add or modify intent attributes and metadata.
   *
   * @Event
   *
   * @see https://stripe.com/docs/api/payment_intents/create
   * @see \Drupal\commerce_stripe\Event\PaymentIntentCreateEvent
   */
  public const PAYMENT_INTENT_CREATE = 'commerce_stripe.payment_intent.create';

  /**
   * Name of the event fired before the Stripe PaymentIntent is updated.
   *
   * This allows subscribers to add or modify intent metadata ONLY.
   *
   * @Event
   *
   * @see https://stripe.com/docs/api/payment_intents/update
   * @see \Drupal\commerce_stripe\Event\PaymentIntentUpdateEvent
   */
  public const PAYMENT_INTENT_UPDATE = 'commerce_stripe.payment_intent.update';

}
