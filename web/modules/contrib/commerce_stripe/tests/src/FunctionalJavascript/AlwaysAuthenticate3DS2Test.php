<?php

namespace Drupal\Tests\commerce_stripe\FunctionalJavascript;

/**
 * Checkout and Payment test.
 *
 * @group commerce_stripe
 * @group commerce_stripe_card_element
 */
class AlwaysAuthenticate3DS2Test extends CardElementTestBase {

  /**
   * Tests customer, with regulations, can check out.
   *
   * This card requires authentication on all transactions, regardless of how
   * the card is set up.
   *
   * Note: When always using SetupIntent, this would cause two authentication
   * modals. One when persisting the payment method and another when confirming
   * the payment intent.
   *
   * @param bool $authenticated
   *   Whether the customer is authenticated.
   * @param bool $pass
   *   Whether to pass or fail the 3DS authentication.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \WebDriver\Exception
   * @throws \Exception
   *
   * @dataProvider dataProviderUserAuthenticatedAndCardAuthentication
   *
   * @group threeDS
   */
  public function test3dsAlwaysAuthenticate(bool $authenticated, bool $pass): void {
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

    $this->fillCreditCardData('4000002760003184');
    $this->submitForm([
      'payment_information[add_payment_method][billing_information][address][0][address][given_name]' => 'Johnny',
      'payment_information[add_payment_method][billing_information][address][0][address][family_name]' => 'Appleseed',
      'payment_information[add_payment_method][billing_information][address][0][address][address_line1]' => '123 New York Drive',
      'payment_information[add_payment_method][billing_information][address][0][address][locality]' => 'New York City',
      'payment_information[add_payment_method][billing_information][address][0][address][administrative_area]' => 'NY',
      'payment_information[add_payment_method][billing_information][address][0][address][postal_code]' => '10001',
    ], 'Continue to review');

    $this->assertCardDetails();
    $this->getSession()->getPage()->pressButton('Pay and complete purchase');

    $this->complete3ds($pass);

    if ($pass) {
      $this->assertWaitForText('Your order number is 1. You can view your order on your account page when logged in.');
    }
    else {
      $this->assertWaitForText('We are unable to authenticate your payment method. Please choose a different payment method and try again.');
    }
  }

}
