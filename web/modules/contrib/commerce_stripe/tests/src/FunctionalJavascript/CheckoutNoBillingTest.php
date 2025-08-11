<?php

namespace Drupal\Tests\commerce_stripe\FunctionalJavascript;

use Drupal\commerce_payment\Entity\PaymentGateway;

/**
 * Checkout without billing test.
 *
 * @group commerce_stripe
 * @group commerce_stripe_card_element
 */
class CheckoutNoBillingTest extends CardElementTestBase {

  /**
   * Tests checkout without billing information.
   *
   * This uses a card which does not trigger SCA or 3DS authentication.
   *
   * @param bool $authenticated
   *   Whether the customer is authenticated.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \WebDriver\Exception
   * @throws \Exception
   *
   * @dataProvider dataProviderUserAuthenticated
   */
  public function testNoBillingCheckout(bool $authenticated): void {
    $payment_gateway = PaymentGateway::load('stripe_testing');
    $configuration = $payment_gateway->getPlugin()->getConfiguration();
    $configuration['collect_billing_information'] = FALSE;
    $payment_gateway->getPlugin()->setConfiguration($configuration);
    $payment_gateway->save();

    if ($authenticated) {
      $customer = $this->createUser();
      $this->drupalLogin($customer);
    }
    $this->drupalGet($this->product->toUrl()->toString());
    $this->submitForm([], 'Add to cart');
    $this->drupalGet('checkout/1');

    if (!$authenticated) {
      $this->submitForm([], 'Continue as Guest');
      $this->getSession()->getPage()->fillField('contact_information[email]', 'guest@example.com');
      $this->getSession()->getPage()->fillField('contact_information[email_confirm]', 'guest@example.com');
    }

    $this->fillCreditCardData('4242424242424242');
    $this->submitForm([], 'Continue to review');

    $this->assertCardDetails();
    $this->submitForm([], 'Pay and complete purchase');

    $this->assertWaitForText('Your order number is 1. You can view your order on your account page when logged in.');
  }

}
