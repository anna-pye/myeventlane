<?php

namespace Drupal\Tests\commerce_stripe\FunctionalJavascript;

use Drupal\Core\Url;

/**
 * Tests checkout with a previously created payment method.
 *
 * @group commerce_stripe
 * @group commerce_stripe_card_element
 */
class CheckoutWithExistingPaymentMethodTest extends CardElementTestBase {

  /**
   * Tests checkout with a previously created payment method.
   *
   * @param string $card_number
   *   The card number.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \WebDriver\Exception
   * @throws \Exception
   *
   * @dataProvider dataProviderExistingPaymentMethodCardNumber
   *
   * @group threeDS
   * @group existing
   * @group on_session
   */
  public function testCheckoutWithExistingPaymentMethod(string $card_number): void {
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

    $this->drupalGet($this->product->toUrl()->toString());
    $this->submitForm([], 'Add to cart');
    $this->drupalGet('checkout/1');
    $this->getSession()->getPage()->pressButton('Continue to review');
    $this->assertSession()->pageTextContains('Payment information');
    $this->assertCardDetails();
    $this->assertSession()->pageTextContains('Order Summary');
    $this->getSession()->getPage()->pressButton('Pay and complete purchase');

    $this->complete3ds(TRUE);

    $this->assertWaitForText('Your order number is 1. You can view your order on your account page when logged in.');
  }

}
