<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\entity\BundleFieldDefinition;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;

/**
 * Provides the cash app payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "stripe_cashapp",
 *   label = @Translation("Cash app (Preview)"),
 * )
 */
class CashApp extends StripePaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method): string {
    $args = [
      '@stripe_cashapp_cashtag' => $payment_method->get('stripe_cashapp_cashtag')->getString(),
      '@stripe_cashapp_buyer_id' => $payment_method->get('stripe_cashapp_buyer_id')->getString(),
    ];
    return $this->t('Cashapp with cashtag @stripe_cashapp_cashtag (@stripe_cashapp_buyer_id)', $args)->render();
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions(): array {
    $fields = parent::buildFieldDefinitions();

    $fields['stripe_cashapp_cashtag'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Cashtag'))
      ->setDescription(t('The Stripe cashtag'))
      ->setRequired(TRUE);

    $fields['stripe_cashapp_buyer_id'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Buyer Id'))
      ->setDescription(t('The Stripe buyer ID'))
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function updatePaymentMethod(PaymentMethodInterface $payment_method, PaymentMethod $stripe_payment_method): void {
    $cashapp = $stripe_payment_method->cashapp;
    $payment_method->set('stripe_cashapp_cashtag', $cashapp->cashtag);
    $payment_method->set('stripe_cashapp_buyer_id', $cashapp->buyer_id);
  }

  /**
   * {@inheritdoc}
   */
  public function updatePayment(PaymentInterface $payment, PaymentIntent $stripe_payment_intent): void {
    // CashApp payments are never completed initially, until a webhook returns,
    // indicating the payment succeeded.
    $payment->setState('authorization');
  }

  /**
   * {@inheritdoc}
   */
  public function getLogos(): array {
    return [
      'cashapp' => $this->t('Cash App'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function canCapturePayment(PaymentInterface $payment): bool {
    // CashApp payments don't actually support capture.
    // We do put the state in 'authorization' to indicate that
    // the CashApp payment is still pending until it is successful.
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function isReusable(): bool {
    // Stripe currently returns a javascript error when
    // attempting to reuse a CashApp payment method.
    return FALSE;
  }

}
