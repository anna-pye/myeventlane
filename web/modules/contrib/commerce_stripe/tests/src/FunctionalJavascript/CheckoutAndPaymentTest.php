<?php

namespace Drupal\Tests\commerce_stripe\FunctionalJavascript;

/**
 * Checkout and Payment test.
 *
 * @group commerce_stripe
 * @group commerce_stripe_card_element
 */
class CheckoutAndPaymentTest extends CardElementTestBase {

  /**
   * Tests whether a customer can check out.
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
  public function testCheckoutAndPayment(bool $authenticated): void {
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

    $this->assertSession()->elementExists('css', 'span.payment-method-icon.payment-method-icon--visa');

    $this->submitForm([
      'payment_information[add_payment_method][billing_information][address][0][address][given_name]' => 'Johnny',
      'payment_information[add_payment_method][billing_information][address][0][address][family_name]' => 'Appleseed',
      'payment_information[add_payment_method][billing_information][address][0][address][address_line1]' => '123 New York Drive',
      'payment_information[add_payment_method][billing_information][address][0][address][locality]' => 'New York City',
      'payment_information[add_payment_method][billing_information][address][0][address][administrative_area]' => 'NY',
      'payment_information[add_payment_method][billing_information][address][0][address][postal_code]' => '10001',
    ], 'Continue to review');

    $this->assertCardDetails();
    $this->submitForm([], 'Pay and complete purchase');

    $this->assertWaitForText('Your order number is 1. You can view your order on your account page when logged in.');
  }

}
