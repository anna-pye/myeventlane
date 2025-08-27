<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;

/**
 * Provides the stripe Alipay payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "stripe_alipay",
 *   label = @Translation("Alipay"),
 * )
 */
class Alipay extends StripePaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method): string {
    $created = date('m/d/Y', $payment_method->getCreatedTime());
    return "Alipay ($created)";
  }

  /**
   * {@inheritdoc}
   */
  public function getLogos(): array {
    return [
      'alipay' => $this->t('Alipay'),
    ];
  }

}
