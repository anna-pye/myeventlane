<?php

namespace Drupal\commerce_stripe_test\EventSubscriber;

use Drupal\commerce_stripe\Event\PaymentIntentUpdateEvent;
use Drupal\commerce_stripe\Event\StripeEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Transaction data subscriber.
 */
class PaymentIntentUpdateSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      StripeEvents::PAYMENT_INTENT_UPDATE => 'addMetadata',
    ];
  }

  /**
   * Adds additional metadata to a transaction.
   *
   * @param \Drupal\commerce_stripe\Event\PaymentIntentUpdateEvent $event
   *   The transaction data event.
   */
  public function addMetadata(PaymentIntentUpdateEvent $event): void {
    $payment = $event->getPayment();
    $metadata = $event->getMetadata();
    // Add the payment's UUID to the Stripe transaction metadata. For example,
    // another service may query Stripe payment transactions and also load the
    // payment from Drupal Commerce over JSON API.
    $metadata['payment_uuid'] = $payment?->uuid();
    $event->setMetadata($metadata);
  }

}
