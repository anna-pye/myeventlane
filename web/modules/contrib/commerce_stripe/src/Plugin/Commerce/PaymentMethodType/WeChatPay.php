<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;

/**
 * Provides the stripe WeChat payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "stripe_wechat_pay",
 *   label = @Translation("WeChat Pay"),
 * )
 */
class WeChatPay extends StripePaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method): string {
    $created = date('m/d/Y', $payment_method->getCreatedTime());
    return "WeChat Pay ($created)";
  }

  /**
   * {@inheritdoc}
   */
  public function getLogos(): array {
    return [
      'wechat_pay' => $this->t('WeChat Pay'),
    ];
  }

}
