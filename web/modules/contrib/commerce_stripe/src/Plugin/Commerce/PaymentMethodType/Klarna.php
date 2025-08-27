<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType;

use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\entity\BundleFieldDefinition;
use Stripe\PaymentMethod;

/**
 * Provides the Klarna payment method type.
 *
 * @CommercePaymentMethodType(
 *   id = "stripe_klarna",
 *   label = @Translation("Klarna (Preview)"),
 * )
 */
class Klarna extends StripePaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method): string {
    $created = date('Y-m-d H:i:s', $payment_method->getCreatedTime());
    return "Klarna ($created)";
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions(): array {
    $fields = parent::buildFieldDefinitions();

    $fields['stripe_klarna_dob'] = BundleFieldDefinition::create('datetime')
      ->setLabel(t('Klarna DOB'))
      ->setDescription(t('The Stripe klarna DOB'))
      ->setSettings([
        'datetime_type' => DateTimeItem::DATETIME_TYPE_DATE,
      ])
      ->setRequired(FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function updatePaymentMethod(PaymentMethodInterface $payment_method, PaymentMethod $stripe_payment_method): void {
    $klarna = $stripe_payment_method->klarna;
    $dob = $klarna->dob ?? [];
    if (!empty($dob)) {
      $dob_string = ($dob['year'] ?? '') . '-' . ($dob['month'] ?? '') . '-' . ($dob['day'] ?? '');
      if (strlen($dob_string) === 10) {
        $payment_method->set('stripe_klarna_dob', $dob_string);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getLogos(): array {
    return [
      'klarna' => $this->t('Klarna'),
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
