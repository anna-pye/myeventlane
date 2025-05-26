<?php

namespace Drupal\Tests\commerce_stripe\FunctionalJavascript;

use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_payment\Entity\PaymentMethod;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\SoftDeclineException;
use Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripeInterface;
use Drupal\Core\Url;

/**
 * Tests checkout with a previously created payment method.
 *
 * @group commerce_stripe
 * @group commerce_stripe_card_element
 */
class CheckoutWithExistingPaymentMethodOffSession extends CardElementTestBase {

  /**
   * Tests checkout with a previously created payment method.
   *
   * @param string $card_number
   *   The card number.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \WebDriver\Exception
   * @throws \Exception
   *
   * @dataProvider dataProviderExistingPaymentMethodCardNumber
   *
   * @group threeDS
   * @group existing
   * @group off_session
   */
  public function testCheckoutWithExistingPaymentMethodOffSession(string $card_number): void {
    $customer = $this->createUser([
      'manage own commerce_payment_method',
    ]);
    $this->drupalLogin($customer);

    $this->drupalGet(Url::fromRoute('entity.commerce_payment_method.add_form', [
      'user' => $customer->id(),
    ]));
    $this->fillCreditCardData($card_number);
    $this->submitForm([
      'add_payment_method[billing_information][address][0][address][given_name]' => 'Johnny',
      'add_payment_method[billing_information][address][0][address][family_name]' => 'Appleseed',
      'add_payment_method[billing_information][address][0][address][address_line1]' => '123 New York Drive',
      'add_payment_method[billing_information][address][0][address][locality]' => 'New York City',
      'add_payment_method[billing_information][address][0][address][administrative_area]' => 'NY',
      'add_payment_method[billing_information][address][0][address][postal_code]' => '10001',
    ], 'Save');

    $this->complete3ds(TRUE, FALSE);

    $this->assertWaitForText('Visa ending in ' . substr($card_number, -4) . ' saved to your payment methods.');
    $this->drupalGet(Url::fromRoute('entity.commerce_payment_method.collection', [
      'user' => $customer->id(),
    ]));
    $this->assertSession()->pageTextContains('Visa ending in ' . substr($card_number, -4));

    // The customer now has a commerce remote id. We need to reload the entity,
    // so that it will be available when it is passed to the order.
    /** @var \Drupal\user\UserInterface $customer */
    $customer = $this->reloadEntity($customer);

    // Create an off_session order with the payment method generated.
    $cart_provider = $this->container->get('commerce_cart.cart_provider');
    $cart_manager = $this->container->get('commerce_cart.cart_manager');

    $cart = $cart_provider->createCart('default', $this->store, $customer);
    $cart_manager->addEntity($cart, $this->product->getDefaultVariation());

    $gateway = PaymentGateway::load('stripe_testing');
    $payment_method = PaymentMethod::load(1);

    $cart->set('billing_profile', $payment_method->getBillingProfile());
    $cart->set('payment_method', $payment_method);
    $cart->set('payment_gateway', $gateway->id());
    $cart->save();

    $plugin = $gateway->getPlugin();
    assert($plugin instanceof StripeInterface);
    $plugin->createPaymentIntent($cart);

    $payment = Payment::create([
      'state' => 'new',
      'amount' => $cart->getBalance(),
      'payment_gateway' => $gateway,
      'payment_method' => $payment_method,
      'order_id' => $cart,
    ]);

    // @todo 4000003800000446 _should_ not require authentication. Supposedly.
    // Discussed with Stripe support in IRC and they could not confirm.
    $this->expectException(SoftDeclineException::class);
    $this->expectExceptionMessage('The payment intent requires action by the customer for authentication');
    try {
      $plugin->createPayment($payment);
    }
    catch (HardDeclineException $e) {
      $this->fail($e->getMessage());
    }
  }

}
