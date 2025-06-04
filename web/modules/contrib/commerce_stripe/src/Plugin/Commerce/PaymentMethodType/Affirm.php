<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;

/**
 * Provides the Affirm payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "stripe_affirm",
 *   label = @Translation("Affirm (Preview)"),
 * )
 */
class Affirm extends StripePaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method): string {
    return $this->t('Affirm')->render();
  }

  /**
   * {@inheritdoc}
   */
  public function getLogos(): array {
    return [
      'affirm' => $this->t('Affirm'),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function isReusable(): bool {
    // Each transaction is a loan that is individually evaluated.
    return FALSE;
  }

}
