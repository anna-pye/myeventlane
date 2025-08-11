<?php

namespace Drupal\Tests\commerce_stripe\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentMethod;
use Drupal\commerce_payment\Exception\DeclineException;
use Drupal\commerce_payment\Exception\SoftDeclineException;
use Drupal\commerce_price\Price;
use Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripeInterface;
use Stripe\PaymentIntent;

/**
 * Tests creating a payment.
 *
 * @group commerce_stripe
 */
class CreatePaymentTest extends StripeIntegrationTestBase {

  /**
   * Tests createPayment.
   *
   * @param string $payment_method_token
   *   The payment method token.
   * @param bool $capture
   *   The capture.
   * @param string $confirmed_status
   *   The confirmed intent status.
   * @param bool $has_intent
   *   Whether the order already has an intent when createPayment() is called.
   *
   * @dataProvider dataProviderCreatePayment
   */
  public function testCreatePayment($payment_method_token, $capture, $confirmed_status, $has_intent) {
    $gateway = $this->generateGateway();
    $plugin = $gateway->getPlugin();
    assert($plugin instanceof StripeInterface);

    $payment_method = PaymentMethod::create([
      'type' => 'credit_card',
      'payment_gateway' => $gateway->id(),
      'payment_gateway_mode' => 'test',
      'remote_id' => $payment_method_token,
    ]);
    $payment_method->save();

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
    $order->set('payment_method', $payment_method);
    $order->set('payment_gateway', $gateway);
    $order->save();
    $this->container->get('commerce_stripe.order_events_subscriber')->destruct();

    $payment = Payment::create([
      'state' => 'new',
      'amount' => $order->getBalance(),
      'payment_gateway' => $gateway->id(),
      'payment_method' => $payment_method->id(),
      'order_id' => $order->id(),
    ]);

    if ($has_intent) {
      // Create & confirm the intent, simulating the customer
      // using the stripe review checkout pane.
      $intent = $plugin->createPaymentIntent($order, ['capture_method' => $capture ? 'automatic' : 'manual']);
      $intent->confirm();
    }

    // Some scenarios expect the payment to fail, and should throw exceptions.
    if ($confirmed_status !== PaymentIntent::STATUS_SUCCEEDED) {
      if ($has_intent) {
        $this->expectException(SoftDeclineException::class);
        $this->expectExceptionMessage('The payment intent requires action by the customer for authentication');
      }
      else {
        $this->expectException(DeclineException::class);
      }
    }

    $plugin->createPayment($payment, $capture);

    $payment = $this->reloadEntity($payment);
    $next_payment_state = $capture ? 'completed' : 'authorization';
    $this->assertEquals($next_payment_state, $payment->getState()->getId());
    $this->assertNotNull($payment->getRemoteId());

    // We can only retrieve the payment intent if created explicitly above,
    // rather than created by createPayment().
    if ($has_intent) {
      $intent = PaymentIntent::retrieve($order->getData('stripe_intent'));
      $this->assertEquals($intent->id, $payment->getRemoteId());
      // Tests metadata set by commerce_stripe_test.
      $this->assertEquals($intent->metadata['payment_uuid'], $payment->uuid());
    }

    $order = $this->reloadEntity($order);
    $this->assertNull($order->getData('stripe_intent'));
    $order->getState()->applyTransitionById('place');
    $order->save();
  }

  /**
   * Data provider for the create payment test.
   *
   * @return \Generator
   *   The test data.
   */
  public function dataProviderCreatePayment() {
    // 3DS 2 authentication must be completed for the payment to be successful.
    yield [
      'pm_card_threeDSecure2Required',
      TRUE,
      PaymentIntent::STATUS_REQUIRES_ACTION,
      TRUE,
    ];
    yield [
      'pm_card_threeDSecure2Required',
      FALSE,
      PaymentIntent::STATUS_REQUIRES_ACTION,
      TRUE,
    ];
    // 3DS authentication may still be performed, but is not required.
    yield [
      'pm_card_threeDSecureOptional',
      TRUE,
      PaymentIntent::STATUS_SUCCEEDED,
      TRUE,
    ];
    // 3DS is supported for this card, but this card is not enrolled in 3DS.
    yield [
      'pm_card_visa',
      TRUE,
      PaymentIntent::STATUS_SUCCEEDED,
      TRUE,
    ];
    // 3DS is not supported on this card and cannot be invoked.
    yield [
      'pm_card_amex_threeDSecureNotSupported',
      TRUE,
      PaymentIntent::STATUS_SUCCEEDED,
      TRUE,
    ];
    // The stripe review pane is not used, and the card allows this.
    yield [
      'pm_card_threeDSecureOptional',
      TRUE,
      PaymentIntent::STATUS_SUCCEEDED,
      FALSE,
    ];
    // The stripe review pane is not used, and the card doesn't allow this.
    yield [
      'pm_card_threeDSecure2Required',
      TRUE,
      PaymentIntent::STATUS_REQUIRES_ACTION,
      FALSE,
    ];
    // A stored card is used card without the stripe review pane, and the card
    // is setup for this.
    yield [
      'pm_card_authenticationRequiredSetupForOffSession',
      TRUE,
      PaymentIntent::STATUS_SUCCEEDED,
      FALSE,
    ];
    // A stored card is used without the stripe review pane, and the card
    // wants authentication regardless of how it was setup.
    yield [
      'pm_card_authenticationRequired',
      TRUE,
      PaymentIntent::STATUS_REQUIRES_ACTION,
      FALSE,
    ];
  }

}
