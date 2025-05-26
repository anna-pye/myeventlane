<?php

namespace Drupal\Tests\commerce_stripe\FunctionalJavascript;

use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Element\NodeElement;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_payment\Entity\PaymentMethod;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Tests\commerce\FunctionalJavascript\CommerceWebDriverTestBase;
use Drupal\Tests\commerce_stripe\Kernel\StripeIntegrationTestBase;

/**
 * Base class forStripe Card Element checkout tests.
 *
 * @group commerce_stripe
 */
abstract class CardElementTestBase extends CommerceWebDriverTestBase {

  /**
   * The product.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected ProductInterface $product;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_number_pattern',
    'commerce_product',
    'commerce_cart',
    'commerce_checkout',
    'commerce_stripe',
  ];

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $variation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'price' => [
        'number' => '9.99',
        'currency_code' => 'USD',
      ],
    ]);

    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $product = $this->createEntity('commerce_product', [
      'type' => 'default',
      'title' => 'My product',
      'variations' => [$variation],
      'stores' => [$this->store->id()],
    ]);
    $this->product = $product;

    $gateway = PaymentGateway::create([
      'id' => 'stripe_testing',
      'label' => 'Stripe',
      'plugin' => 'stripe',
      'configuration' => [
        'payment_method_types' => ['credit_card'],
        'publishable_key' => StripeIntegrationTestBase::TEST_PUBLISHABLE_KEY,
        'secret_key' => StripeIntegrationTestBase::TEST_SECRET_KEY,
      ],
    ]);
    $gateway->save();

    // Cheat so we don't need JS to interact w/ Address field widget.
    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $customer_form_display */
    $customer_form_display = EntityFormDisplay::load('profile.customer.default');
    $address_component = $customer_form_display->getComponent('address');
    $address_component['settings']['default_country'] = 'US';
    $customer_form_display->setComponent('address', $address_component);
    $customer_form_display->save();
    $this->drupalLogout();

  }

  /**
   * Data provider to provide a pass or truthy data set.
   *
   * @return \Generator
   *   The data.
   */
  public static function dataProviderUserAuthenticated(): \Generator {
    yield [TRUE];
    yield [FALSE];
  }

  /**
   * Data provider for user authentication and card authentication.
   *
   * @return \Generator
   *   The data.
   */
  public static function dataProviderUserAuthenticatedAndCardAuthentication(): \Generator {
    // Logged in, card authorized.
    yield [TRUE, TRUE];
    // Anonymous, card authorized.
    yield [FALSE, TRUE];
    // Logged in, card unauthorized.
    yield [TRUE, FALSE];
    // Anonymous, card unauthorized.
    yield [FALSE, FALSE];
  }

  /**
   * Data provider for card numbers when testing existing payment methods.
   *
   * @return \Generator
   *   The data.
   */
  public static function dataProviderExistingPaymentMethodCardNumber(): \Generator {
    // These can be added, but must go through one authentication approval via
    // an on-session payment intent.
    yield ['4000002500003155'];
    yield ['4000002760003184'];
    // This card requires authentication for one-time and other on-session
    // payments. However, all off-session payments will succeed as if the card
    // has been previously set up.
    yield ['4000003800000446'];
  }

  /**
   * Fills the credit card form inputs.
   *
   * @param string $card_number
   *   The card number.
   * @param string|null $card_exp
   *   (optional) The card expiration.
   * @param string $card_cvv
   *   (optional) The card CVV.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \WebDriver\Exception
   * @throws \Exception
   */
  protected function fillCreditCardData(string $card_number, ?string $card_exp = NULL, string $card_cvv = '123'): void {
    $this->switchToElementFrame('card-number-element');
    $element = $this->getSession()->getPage()->findField('cardnumber');
    $this->fieldTypeInput($element, $card_number);
    $this->getSession()->switchToIFrame();
    $this->assertSession()->pageTextNotContains('Your card number is invalid.');

    if (!$card_exp) {
      $card_exp = '03' . (date('y') + 1);
    }
    $this->switchToElementFrame('expiration-element');
    $element = $this->getSession()->getPage()->findField('exp-date');
    $this->fieldTypeInput($element, $card_exp);
    $this->getSession()->switchToIFrame();

    $this->switchToElementFrame('security-code-element');
    $this->getSession()->getPage()->fillField('cvc', $card_cvv);
    $this->getSession()->switchToIFrame();
  }

  /**
   * Fills an inputs values by simulated typing.
   *
   * @param \Behat\Mink\Element\NodeElement $element
   *   The element.
   * @param string $value
   *   The value.
   *
   * @throws \WebDriver\Exception
   * @throws \Behat\Mink\Exception\DriverException
   */
  protected function fieldTypeInput(NodeElement $element, string $value): void {
    $driver = $this->getSession()->getDriver();
    $element->click();
    if ($driver instanceof Selenium2Driver) {
      $wd_element = $driver->getWebDriverSession()->element('xpath', $element->getXpath());
      foreach (str_split($value) as $char) {
        $wd_element->postValue(['text' => $char]);
        usleep(100);
      }
    }
    $element->blur();
  }

  /**
   * Asserts text will become visible on the page.
   *
   * @param string $text
   *   The text.
   * @param int $wait
   *   The wait time, in seconds.
   *
   * @return bool
   *   Returns TRUE if operation succeeds.
   *
   * @throws \Exception
   */
  public function assertWaitForText(string $text, int $wait = 20): bool {
    $last_exception = NULL;
    $stopTime = time() + $wait;
    while (time() < $stopTime) {
      try {
        $this->assertSession()->pageTextContains($text);
        return TRUE;
      }
      catch (\Exception $e) {
        // If the text has not been found, keep waiting.
        $last_exception = $e;
      }
      usleep(250000);
    }
    $this->createScreenshot('../challenge_frame_wtf.png');
    throw $last_exception;
  }

  /**
   * Waits for a frame to become available and then switches to it.
   *
   * @param string $name
   *   The frame name.
   * @param int $wait
   *   The wait time, in seconds.
   *
   * @return bool
   *   Returns TRUE if operation succeeds.
   *
   * @throws \Exception
   */
  public function switchToFrame(string $name, int $wait = 20): bool {
    $last_exception = NULL;
    $stopTime = time() + $wait;
    while (time() < $stopTime) {
      try {
        $element = $this->assertSession()->elementExists('xpath', "//iframe[@id='$name' or @name='$name' or starts-with(@name, '$name')]");
        $this->getSession()->switchToIFrame($element->getAttribute('name'));
        sleep(1);
        return TRUE;
      }
      catch (\Exception $e) {
        // If the frame has not been found, keep waiting.
        $last_exception = $e;
      }
      usleep(250000);
    }
    throw $last_exception;
  }

  /**
   * Completes 3DS authentication using Stripe's modal.
   *
   * @param bool $pass
   *   Whether to pass or fail the 3DS authentication.
   * @param bool $payment
   *   Whether this is a payment or non-payment 3DS.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Exception
   */
  protected function complete3ds(bool $pass, bool $payment = TRUE): void {
    $text = '3D Secure 2 Test Page';
    $this->waitForStripe();
    $this->switchToFrame('__privateStripeFrame');
    $this->switchToFrame('challengeFrame');
    $this->assertWaitForText($text);
    $button = $pass ? 'Complete' : 'Fail';
    $this->getSession()->getPage()->pressButton($button);
    $this->getSession()->switchToWindow();
  }

  /**
   * Switch to the first iframe which ancestor is the given div element id.
   *
   * @param string $element_id
   *   The div element id.
   *
   * @throws \Exception
   */
  protected function switchToElementFrame(string $element_id): void {
    $iframe = $this->getSession()->getPage()
      ->find('xpath', '//div[@id="' . $element_id . '"]//iframe')
      ->getAttribute('name');
    $this->switchToFrame($iframe);
  }

  /**
   * Helper method to wait for Stripe actions on the client.
   */
  protected function waitForStripe(): void {
    // @todo better assertion to wait for the form to submit.
    sleep(6);
  }

  /**
   * Asserts that the card details are shown in the pane summary.
   *
   * @throws \Exception
   */
  protected function assertCardDetails(): void {
    $this->assertWaitForText('Visa ending');
    $payment_method = PaymentMethod::load(1);
    $this->assertWaitForText('Visa ending in ' . $payment_method->get('card_number')->value);
    $this->assertWaitForText('Expires ' . $payment_method->get('card_exp_month')->value . '/' . $payment_method->get('card_exp_year')->value);
  }

}
