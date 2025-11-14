<?php

namespace Drupal\Tests\commerce_stripe\Kernel;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Stripe integration test base.
 */
abstract class StripeIntegrationTestBase extends OrderKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_payment',
    'commerce_stripe',
    'commerce_stripe_test',
  ];

  /**
   * The development publishable key.
   */
  const TEST_PUBLISHABLE_KEY = 'pk_test_EnquIXQLnqkP0knhcyRczqe600Iq21pkdd';

  /**
   * The development secret key.
   */
  const TEST_SECRET_KEY = 'sk_test_4g69Cl9vOTJxe7bUmy5TRgWE00ytQmCnep';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_payment_method');
    $this->installEntitySchema('commerce_payment');
  }

  /**
   * Generate a payment gateway for testing.
   *
   * @return \Drupal\Core\Entity\EntityInterface|\Drupal\commerce_payment\Entity\PaymentGatewayInterface
   *   The Stripe gateway.
   */
  protected function generateGateway() {
    $gateway = PaymentGateway::create([
      'id' => $this->randomMachineName(),
      'label' => $this->randomString(),
      'plugin' => 'stripe',
      'configuration' => [
        'payment_method_types' => ['credit_card'],
        'publishable_key' => static::TEST_PUBLISHABLE_KEY,
        'secret_key' => static::TEST_SECRET_KEY,
      ],
    ]);
    $gateway->save();
    return $this->reloadEntity($gateway);
  }

}
