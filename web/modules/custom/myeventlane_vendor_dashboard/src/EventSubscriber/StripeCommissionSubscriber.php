<?php

namespace Drupal\myeventlane_vendor_dashboard\EventSubscriber;

use Drupal\commerce_payment\Event\PaymentEvent;
use Drupal\commerce_payment\Event\PaymentEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Stripe\Stripe;
use Stripe\PaymentIntent;

/**
 * Adds application fee and vendor destination to Stripe payments.
 */
class StripeCommissionSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      PaymentEvents::PAYMENT_PREPROCESS => 'onPaymentPreprocess',
    ];
  }

  public function onPaymentPreprocess(PaymentEvent $event) {
    $payment = $event->getPayment();
    $order = $payment->getOrder();
    $gateway = $payment->getPaymentGateway();

    // Only modify Stripe payments
    if ($gateway->getPluginId() !== 'commerce_stripe') {
      return;
    }

    // Load Stripe API key from config
    $config = \Drupal::config('myeventlane_vendor_dashboard.settings');
    $mode = $config->get('mode') ?? 'test';
    $secret = $mode === 'live' ? $config->get('secret_key_live') : $config->get('secret_key_test');
    Stripe::setApiKey($secret);

    // Load vendor profile
    $store = $order->getStore();
    $uid = $store->getOwnerId();
    $profiles = \Drupal::entityTypeManager()
      ->getStorage('profile')
      ->loadByProperties(['uid' => $uid, 'type' => 'vendor_profile']);
    $profile = reset($profiles);
    $stripe_account = $profile ? $profile->get('field_stripe_account_id')->value : NULL;

    if (!$stripe_account) {
      \Drupal::logger('stripe_commission')->error('Missing Stripe account for vendor UID @uid', ['@uid' => $uid]);
      return;
    }

    // Calculate amounts
    $amount = $payment->getAmount()->getNumber(); // e.g. 45.00
    $currency = $payment->getAmount()->getCurrencyCode();
    $amount_cents = round($amount * 100);
    $commission_cents = round($amount_cents * 0.015); // 1.5%

    // Create PaymentIntent
    try {
      $intent = PaymentIntent::create([
        'amount' => $amount_cents,
        'currency' => strtolower($currency),
        'application_fee_amount' => $commission_cents,
        'transfer_data' => [
          'destination' => $stripe_account,
        ],
        'description' => 'MyEventLane Order #' . $order->id(),
      ]);
    }
    catch (\Exception $e) {
      \Drupal::logger('stripe_commission')->error('Failed to create PaymentIntent: @msg', ['@msg' => $e->getMessage()]);
      return;
    }

    // Attach the Stripe PaymentIntent ID to the payment
    $payment->setRemoteId($intent->id);
    $payment->save();
  }
}
