<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\CreditCard as CreditCardHelper;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\entity\BundleFieldDefinition;
use Stripe\PaymentMethod;

/**
 * Provides the stripe card payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "stripe_card",
 *   label = @Translation("Card"),
 * )
 */
class Card extends StripePaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method): string {
    $stripe_card_type = CreditCardHelper::getType($payment_method->get('stripe_card_type')->getString());
    $args = [
      '@stripe_card_type' => $stripe_card_type->getLabel(),
      '@stripe_card_number' => $payment_method->get('stripe_card_number')->getString(),
    ];
    $label = $this->t('@stripe_card_type ending in @stripe_card_number', $args)->render();
    $stripe_wallet_type = $payment_method->get('stripe_card_wallet_type')->getString();
    if ($stripe_wallet_type) {
      $label = sprintf('%s (%s)', $label, $stripe_wallet_type);
    }
    return $label;
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions(): array {
    $fields = parent::buildFieldDefinitions();

    $fields['stripe_card_type'] = BundleFieldDefinition::create('list_string')
      ->setLabel(t('Stripe Card type'))
      ->setDescription(t('The credit card type.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values_function', [
        CreditCardHelper::class,
        'getTypeLabels',
      ]);

    $fields['stripe_card_number'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Stripe Card number'))
      ->setDescription(t('The last few digits of the credit card number'))
      ->setRequired(TRUE);

    // stripe_card_exp_month and stripe_card_exp_year are not required because
    // they might not be known (tokenized non-reusable payment methods).
    $fields['stripe_card_exp_month'] = BundleFieldDefinition::create('integer')
      ->setLabel(t('Stripe Card expiration month'))
      ->setDescription(t('The credit card expiration month.'))
      ->setSetting('size', 'tiny');

    $fields['stripe_card_exp_year'] = BundleFieldDefinition::create('integer')
      ->setLabel(t('Stripe Card expiration year'))
      ->setDescription(t('The credit card expiration year.'))
      ->setSetting('size', 'small');

    $fields['stripe_card_wallet_type'] = BundleFieldDefinition::create('string')
      ->setLabel(t('Stripe Wallet type'))
      ->setDescription(t('The card wallet type'))
      ->setRequired(FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function updatePaymentMethod(PaymentMethodInterface $payment_method, PaymentMethod $stripe_payment_method): void {
    /** @var \Stripe\Card $card */
    $card = $stripe_payment_method->card;
    $payment_method->set('stripe_card_type', self::mapCreditCardType($card->brand));
    $payment_method->set('stripe_card_number', $card->last4);
    $payment_method->set('stripe_card_exp_month', $card->exp_month);
    $payment_method->set('stripe_card_exp_year', $card->exp_year);
    $expires = CreditCardHelper::calculateExpirationTimestamp($card->exp_month, $card->exp_year);
    $payment_method->setExpiresTime($expires);
    if ($wallet_type = ($card->wallet->type ?? NULL)) {
      $payment_method->set('stripe_card_wallet_type', $wallet_type);
    }
  }

  /**
   * Maps the Stripe credit card type to a Commerce credit card type.
   *
   * @param string $card_type
   *   The Stripe credit card type.
   *
   * @return string
   *   The Commerce credit card type.
   */
  protected static function mapCreditCardType(string $card_type): string {
    $map = [
      'amex' => 'amex',
      'diners' => 'dinersclub',
      'discover' => 'discover',
      'jcb' => 'jcb',
      'mastercard' => 'mastercard',
      'visa' => 'visa',
      'unionpay' => 'unionpay',
    ];
    if (!isset($map[$card_type])) {
      throw new HardDeclineException(sprintf('Unsupported credit card type "%s".', $card_type));
    }

    return $map[$card_type];
  }

  /**
   * {@inheritdoc}
   */
  public function getLogos(): array {
    return [
      'amex' => $this->t('American Express'),
      'dinersclub' => $this->t('Diners Club'),
      'discover' => $this->t('Discover Card'),
      'jcb' => $this->t('JCB'),
      'maestro' => $this->t('Maestro'),
      'mastercard' => $this->t('Mastercard'),
      'visa' => $this->t('Visa'),
      'unionpay' => $this->t('UnionPay'),
      'applepay' => $this->t('Apple Pay'),
      'googlepay' => $this->t('Google Pay'),
    ];
  }

}
