<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\entity\BundleFieldDefinition;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;

/**
 * Provides the bank account payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "stripe_us_bank_account",
 *   label = @Translation("ACH Direct Debit"),
 * )
 */
class USBankAccount extends StripePaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method): string {
    $args = [
      '@stripe_us_bank_account_bank_name' => $payment_method->get('stripe_us_bank_account_bank_name')->getString(),
      '@stripe_us_bank_account_type' => $payment_method->get('stripe_us_bank_account_type')->getString(),
      '@stripe_us_bank_account_account_last4' => $payment_method->get('stripe_us_bank_account_account_last4')->getString(),
    ];
    return $this->t('@stripe_us_bank_account_bank_name, @stripe_us_bank_account_type ending in @stripe_us_bank_account_account_last4', $args)->render();
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions(): array {
    $fields = parent::buildFieldDefinitions();

    $fields['stripe_us_bank_account_bank_name'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Stripe bank name'))
      ->setDescription(t('The bank name'))
      ->setRequired(TRUE);

    $fields['stripe_us_bank_account_type'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Stripe bank account type'))
      ->setDescription(t('The bank account type'))
      ->setRequired(TRUE);

    $fields['stripe_us_bank_account_account_last4'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Stripe bank account number'))
      ->setDescription(t('The last four digits of the bank account number'))
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function updatePaymentMethod(PaymentMethodInterface $payment_method, PaymentMethod $stripe_payment_method): void {
    /** @var \Stripe\BankAccount $us_bank_account */
    $us_bank_account = $stripe_payment_method->us_bank_account;
    $payment_method->set('stripe_us_bank_account_bank_name', $us_bank_account->bank_name);
    $payment_method->set('stripe_us_bank_account_type', $us_bank_account->account_type);
    $payment_method->set('stripe_us_bank_account_account_last4', $us_bank_account->last4);
  }

  /**
   * {@inheritdoc}
   */
  public function updatePayment(PaymentInterface $payment, PaymentIntent $stripe_payment_intent): void {
    // ACH payments are never completed initially, until a webhook returns,
    // indicating the payment succeeded.
    $payment->setState('authorization');
  }

  /**
   * {@inheritdoc}
   */
  public function getLogos(): array {
    return [
      'us_bank_account' => $this->t('ACH Direct Debit'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function canCapturePayment(PaymentInterface $payment): bool {
    // ACH payments don't actually support capture.
    // We do put the state in 'authorization' to indicate that
    // the ACH payment is still pending until it is successful,
    // which is indicated by a webhook.
    return FALSE;
  }

  /**
   * {@inheritDoc}
   */
  public function onIntentCreateAttributes(array &$intent_attributes_array): void {
    // We only support instant verification for ACH.
    // micro deposit verification takes several days,
    // and we can't place the order until it has been verified.
    // To support micro deposits, we would need to
    // have the customer set up a payment method outside
    // the checkout flow. When they verify the micro deposits,
    // then the payment method would be available during checkout.
    // This would require an additional form for micro deposit
    // verification, and management of a pending payment method.
    if (!isset($intent_attributes_array['payment_method'])) {
      $intent_attributes_array['payment_method_options']['us_bank_account']['verification_method'] = 'instant';
    }
  }

}
