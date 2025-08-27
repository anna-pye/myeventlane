<?php

namespace Drupal\Tests\commerce_stripe\Kernel;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethod;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripeInterface;
use Drupal\user\Entity\User;
use Prophecy\Argument;
use Stripe\PaymentIntent;

/**
 * Payment intent tests.
 *
 * @group commerce_stripe
 */
class PaymentIntentTest extends StripeIntegrationTestBase {

  /**
   * Tests creating payment intents.
   *
   * @param string $payment_method_token
   *   The test payment method token.
   * @param bool $capture
   *   The capture.
   * @param string $initial_status
   *   The initial payment intent status.
   * @param string $confirmed_status
   *   The confirmed payment intent status.
   * @param bool $payment_received
   *   Whether the payment is received.
   *
   * @dataProvider dataProviderCreatePaymentIntent
   */
  public function testCreatePaymentIntent(string $payment_method_token, bool $capture, string $initial_status, string $confirmed_status, bool $payment_received) {
    $passed_payment = NULL;
    $payment_passed_id = 'pm_card_visa';
    $payment_amount = new Price('15.00', 'USD');
    if ($payment_received) {
      $passed_payment = $this->prophesize(PaymentInterface::class);
      $passed_payment_method = $this->prophesize(PaymentMethodInterface::class);

      $passed_payment_method->getRemoteId()->willReturn($payment_passed_id);
      $passed_payment->getPaymentMethod()->willReturn($passed_payment_method->reveal());
      $passed_payment->getAmount()->willReturn($payment_amount);
      $passed_payment = $passed_payment->reveal();
    }
    // As we are really calling the API, we need a hack to assert if it was
    // called with the right params. Let's abuse metadata for that.
    $metadata = ['used_payment_method_remote_id' => $payment_received ? $payment_passed_id : $payment_method_token];

    $gateway = $this->generateGateway();
    $plugin = $gateway->getPlugin();
    assert($plugin instanceof StripeInterface);
    $payment_method = $this->prophesize(PaymentMethodInterface::class);
    $payment_method->getRemoteId()->willReturn($payment_method_token);

    $order = $this->prophesize(OrderInterface::class);
    $order->get('payment_method')->willReturn((object) [
      'entity' => $payment_method->reveal(),
    ]);
    $order->getBalance()->willReturn($payment_amount);
    $order->getStoreId()->willReturn(1111);
    $order->id()->willReturn(9999);
    $order->getCustomer()->willReturn(User::getAnonymousUser());
    $order->setData('stripe_intent', Argument::containingString('pi_'))->willReturn($order->reveal());
    $order->save()->willReturn(NULL);

    $intent = $plugin->createPaymentIntent($order->reveal(), [
      'capture_method' => $capture ? 'automatic' : 'manual',
      'metadata' => $metadata,
    ], $passed_payment);
    $this->assertEquals($capture ? 'automatic' : 'manual', $intent->capture_method);
    $this->assertEquals($initial_status, $intent->status);
    $this->assertEquals($intent->currency, 'usd');
    $this->assertEquals($intent->amount, 1500);

    if ($payment_received) {
      $this->assertEquals($intent->metadata->used_payment_method_remote_id, $payment_passed_id);
    }
    else {
      $this->assertEquals($intent->metadata->used_payment_method_remote_id, $payment_method_token);
    }

    $intent = $intent->confirm();
    $this->assertEquals($confirmed_status, $intent->status);
  }

  /**
   * Tests creating payment intents with additional parameters.
   */
  public function testCreatePaymentIntentAdditionalParameters() {
    $gateway = $this->generateGateway();
    $plugin = $gateway->getPlugin();
    assert($plugin instanceof StripeInterface);

    $order_payment_method = $this->prophesize(PaymentMethodInterface::class);
    $order_payment_method->getRemoteId()->willReturn('pm_card_threeDSecureOptional');
    $order = $this->prophesize(OrderInterface::class);
    $order->get('payment_method')->willReturn((object) [
      'entity' => $order_payment_method->reveal(),
    ]);
    $order->getBalance()->willReturn(new Price('15.00', 'USD'));
    $order->getStoreId()->willReturn(1111);
    $order->id()->willReturn(9999);
    $order->getCustomer()->willReturn(User::getAnonymousUser());
    $order->setData('stripe_intent', Argument::containingString('pi_'))->willReturn($order->reveal());
    $order->save()->willReturn(NULL);

    $payment_payment_method = $this->prophesize(PaymentMethodInterface::class);
    $payment_payment_method->getRemoteId()->willReturn('pm_card_threeDSecure2Required');
    $payment = $this->prophesize(PaymentInterface::class);
    $payment->getPaymentMethod()->willReturn($payment_payment_method->reveal());
    $payment->getAmount()->willReturn(new Price('22.00', 'GBP'));

    $intent_attributes = [
      'capture_method' => 'manual',
      'description' => 'a test intent',
    ];

    $intent = $plugin->createPaymentIntent($order->reveal(), $intent_attributes, $payment->reveal());
    $this->assertEquals(PaymentIntent::STATUS_REQUIRES_CONFIRMATION, $intent->status);

    // The passed intent attributes should override the defaults.
    $this->assertEquals('manual', $intent->capture_method);
    $this->assertEquals('a test intent', $intent->description);

    // Price and payment method come from payment not order.
    $this->assertEquals($intent->currency, 'gbp');
    $this->assertEquals($intent->amount, 2200);
  }

  /**
   * Tests that the order total syncs the payment intent total.
   *
   * @param bool $deleted_gateway
   *   Boolean to determine if the test should delete the gateway.
   *
   * @dataProvider dataProviderOrderSync
   */
  public function testIntentOrderTotalSync($deleted_gateway) {
    $gateway = $this->generateGateway();
    $plugin = $gateway->getPlugin();
    assert($plugin instanceof StripeInterface);

    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'uid' => 0,
      'state' => 'draft',
    ]);
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('10.50', 'USD'),
    ]);
    $order_item->save();
    $order->addItem($order_item);
    $payment_method = PaymentMethod::create([
      'type' => 'credit_card',
      'payment_gateway' => $gateway->id(),
      'payment_gateway_mode' => 'test',
      'remote_id' => 'pm_card_threeDSecure2Required|',
    ]);
    $payment_method->save();
    $order->set('payment_method', $payment_method);
    $order->set('payment_gateway', $gateway);
    $order->save();

    $intent = $plugin->createPaymentIntent($order);

    $this->assertEquals(1050, $intent->amount);

    if ($deleted_gateway) {
      $gateway->delete();
    }

    $order = $this->reloadEntity($order);
    $order->addAdjustment(new Adjustment([
      'type' => 'custom',
      'label' => 'Back to school discount',
      'amount' => new Price('-5.00', 'USD'),
    ]));
    $order->save();
    // Flush pending updates.
    $this->container->get('commerce_stripe.order_events_subscriber')->destruct();

    $this->assertEquals('5.50', $order->getBalance()->getNumber());
    $intent = PaymentIntent::retrieve($intent->id);
    // If the payment gateway was deleted, the payment intent could not
    // be updated.
    $this->assertEquals($deleted_gateway ? 1050 : 550, $intent->amount);
  }

  /**
   * Tests the intent sync does not fail if order was emptied.
   */
  public function testIntentEmptyOrderSync() {
    $gateway = $this->generateGateway();
    $plugin = $gateway->getPlugin();
    assert($plugin instanceof StripeInterface);

    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'uid' => 0,
      'state' => 'draft',
    ]);
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('10.50', 'USD'),
    ]);
    $order_item->save();
    $order->addItem($order_item);
    $payment_method = PaymentMethod::create([
      'type' => 'credit_card',
      'payment_gateway' => $gateway->id(),
      'payment_gateway_mode' => 'test',
      'remote_id' => 'pm_card_threeDSecure2Required|',
    ]);
    $payment_method->save();
    $order->set('payment_method', $payment_method);
    $order->set('payment_gateway', $gateway);
    $order->save();

    $intent = $plugin->createPaymentIntent($order);

    $this->assertEquals(1050, $intent->amount);

    $order->addAdjustment(new Adjustment([
      'type' => 'custom',
      'label' => 'Back to school discount',
      'amount' => new Price('-5.00', 'USD'),
    ]));
    $order->save();
    // Flush pending updates.
    $this->container->get('commerce_stripe.order_events_subscriber')->destruct();

    $this->assertEquals('5.50', $order->getBalance()->getNumber());
    $intent = PaymentIntent::retrieve($intent->id);
    $this->assertEquals(550, $intent->amount);

    $order->setAdjustments([]);
    $order->removeItem($order_item);
    $order->save();
    // Flush pending updates.
    $this->container->get('commerce_stripe.order_events_subscriber')->destruct();

    $this->assertNull($order->getBalance());
    $intent = PaymentIntent::retrieve($intent->id);
    $this->assertEquals(550, $intent->amount, 'Intent has the same previous value');

    $order->addItem($order_item);
    $order->save();
    // Flush pending updates.
    $this->container->get('commerce_stripe.order_events_subscriber')->destruct();

    $this->assertEquals('10.50', $order->getBalance()->getNumber());
    $intent = PaymentIntent::retrieve($intent->id);
    $this->assertEquals(1050, $intent->amount);
  }

  /**
   * Data provider for createPaymentIntent.
   *
   * @return \Generator
   *   The test data.
   */
  public static function dataProviderCreatePaymentIntent() {
    // 3DS 2 authentication must be completed for the payment to be successful.
    yield [
      'pm_card_threeDSecure2Required',
      TRUE,
      PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
      PaymentIntent::STATUS_REQUIRES_ACTION,
      FALSE,
    ];
    yield [
      'pm_card_threeDSecure2Required',
      FALSE,
      PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
      PaymentIntent::STATUS_REQUIRES_ACTION,
      FALSE,
    ];
    // 3DS authentication may still be performed, but is not required.
    yield [
      'pm_card_threeDSecureOptional',
      TRUE,
      PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
      PaymentIntent::STATUS_SUCCEEDED,
      FALSE,
    ];
    // 3DS is supported for this card,
    // but this card is not enrolled in 3D Secure.
    yield [
      'pm_card_visa',
      TRUE,
      PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
      PaymentIntent::STATUS_SUCCEEDED,
      FALSE,
    ];
    // 3DS is not supported on this card and cannot be invoked.
    yield [
      'pm_card_amex_threeDSecureNotSupported',
      TRUE,
      PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
      PaymentIntent::STATUS_SUCCEEDED,
      FALSE,
    ];
    // A payment was passed, so we use that payment gateway token instead.
    yield [
      'pm_card_amex_threeDSecureNotSupported',
      TRUE,
      PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
      PaymentIntent::STATUS_SUCCEEDED,
      TRUE,
    ];
  }

  /**
   * Data provider for testing payment intent updates.
   *
   * @return \Generator
   *   The test data.
   */
  public static function dataProviderOrderSync() {
    yield [FALSE];
    yield [TRUE];
  }

}
