<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;

/**
 * Provides the amazon pay payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "stripe_amazon_pay",
 *   label = @Translation("Amazon Pay (Preview)"),
 * )
 */
class AmazonPay extends StripePaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method): string {
    return $this->t('Amazon Pay')->render();
  }

  /**
   * {@inheritdoc}
   */
  public function getLogos(): array {
    return [
      'amazon_pay' => $this->t('Amazon Pay'),
    ];
  }

}
