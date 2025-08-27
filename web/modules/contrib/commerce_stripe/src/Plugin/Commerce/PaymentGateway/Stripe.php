<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Exception\SoftDeclineException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_stripe\ErrorHelper;
use Drupal\commerce_stripe\Event\PaymentIntentCreateEvent;
use Drupal\commerce_stripe\Event\PaymentIntentUpdateEvent;
use Drupal\commerce_stripe\Event\StripeEvents;
use Drupal\commerce_stripe\IntentHelper;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\profile\Entity\ProfileInterface;
use Stripe\ApiRequestor;
use Stripe\Balance;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Exception\ApiErrorException;
use Stripe\HttpClient\CurlClient;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Refund;
use Stripe\Stripe as StripeLibrary;
use Stripe\StripeObject;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides the Stripe payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "stripe",
 *   label = "Stripe Card Element",
 *   display_label = "Stripe Card Element",
 *   forms = {
 *     "add-payment-method" = "Drupal\commerce_stripe\PluginForm\Stripe\PaymentMethodAddForm",
 *   },
 *   payment_method_types = {"credit_card"},
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard",
 *   "visa", "unionpay"
 *   },
 *   js_library = "commerce_stripe/form",
 *   requires_billing_information = FALSE,
 * )
 */
class Stripe extends OnsitePaymentGatewayBase implements StripeInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuidService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->moduleExtensionList = $container->get('extension.list.module');
    $instance->uuidService = $container->get('uuid');
    $instance->init();
    return $instance;
  }

  /**
   * Re-initializes the SDK after the plugin is unserialized.
   */
  public function __wakeup(): void {
    parent::__wakeup();

    $this->init();
  }

  /**
   * Initializes the SDK.
   */
  protected function init(): void {
    $extension_info = $this->moduleExtensionList->getExtensionInfo('commerce_stripe');
    $version = !empty($extension_info['version']) ? $extension_info['version'] : '8.x-1.0-dev';
    StripeLibrary::setAppInfo('Centarro Commerce for Drupal', $version, 'https://www.drupal.org/project/commerce_stripe', 'pp_partner_Fa3jTqCJqTDtHD');

    // If Drupal is configured to use a proxy for outgoing requests, make sure
    // that the proxy CURLOPT_PROXY setting is passed to the Stripe SDK client.
    $http_client_config = Settings::get('http_client_config');
    if (!empty($http_client_config['proxy']['https'])) {
      $curl = new CurlClient([CURLOPT_PROXY => $http_client_config['proxy']['https']]);
      ApiRequestor::setHttpClient($curl);
    }

    if (!empty($this->configuration['access_token'])) {
      StripeLibrary::setApiKey($this->configuration['access_token']);
    }
    else {
      StripeLibrary::setApiKey($this->configuration['secret_key']);
    }
    StripeLibrary::setApiVersion('2019-12-03');
  }

  /**
   * {@inheritdoc}
   */
  public function getPublishableKey(): ?string {
    return $this->configuration['publishable_key'];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'authentication_method' => 'stripe_connect',
      'publishable_key' => '',
      'access_token' => '',
      'stripe_user_id' => '',
      'secret_key' => '',
      'enable_credit_card_icons' => TRUE,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['access_token'] = [
      '#type' => 'value',
      '#default_value' => $this->configuration['access_token'],
    ];
    $form['stripe_user_id'] = [
      '#type' => 'value',
      '#default_value' => $this->configuration['stripe_user_id'],
    ];
    $form['authentication_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Authentication Method'),
      '#description' => $this->t('When "Stripe connect" is selected, connecting to Stripe is done from the payment gateway list page.'),
      '#options' => [
        'stripe_connect' => $this->t('Stripe connect (Preferred)'),
        'api_keys' => $this->t('API keys'),
      ],
      '#default_value' => !empty($this->configuration['secret_key']) ? 'api_keys' : $this->configuration['authentication_method'],
      '#disabled' => !empty($this->configuration['access_token']),
    ];
    $stripe_connect_states = [
      'invisible' => [
        ':input[name="configuration[' . $this->pluginId . '][authentication_method]"]' => ['value' => 'stripe_connect'],
      ],
    ];
    $form['publishable_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publishable Key'),
      '#default_value' => $this->configuration['publishable_key'],
      '#states' => $stripe_connect_states,
      '#required' => FALSE,
    ];

    $form['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret Key'),
      '#default_value' => $this->configuration['secret_key'],
      '#states' => $stripe_connect_states,
      '#required' => FALSE,
    ];

    $form['validate_api_keys'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate API keys upon form submission.'),
      '#states' => $stripe_connect_states,
      '#default_value' => TRUE,
    ];

    $form['enable_credit_card_icons'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Credit Card Icons'),
      '#description' => $this->t('Enabling this setting will display credit card icons in the payment section during checkout.'),
      '#default_value' => $this->configuration['enable_credit_card_icons'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::validateConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      // Validate the secret key.
      $expected_livemode = $values['mode'] === 'live';
      if ($values['validate_api_keys'] && $values['authentication_method'] === 'api_keys') {
        try {
          StripeLibrary::setApiKey($values['secret_key']);
          // Make sure we use the right mode for the secret keys.
          if (Balance::retrieve()->offsetGet('livemode') !== $expected_livemode) {
            $form_state->setError($form['secret_key'], $this->t('The provided secret key is not for the selected mode (@mode).', ['@mode' => $values['mode']]));
          }
        }
        catch (ApiErrorException) {
          $form_state->setError($form['secret_key'], $this->t('Invalid secret key.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['publishable_key'] = $values['publishable_key'];
      $this->configuration['authentication_method'] = $values['authentication_method'];
      if ($values['authentication_method'] === 'api_keys') {
        $this->configuration['secret_key'] = $values['secret_key'];
      }
      else {
        $this->configuration['access_token'] = $values['access_token'];
        $this->configuration['stripe_user_id'] = $values['stripe_user_id'];
      }
      $this->configuration['enable_credit_card_icons'] = $values['enable_credit_card_icons'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE): void {
    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    assert($payment_method instanceof PaymentMethodInterface);
    $order = $payment->getOrder();
    assert($order instanceof OrderInterface);
    $intent_id = $order->getData('stripe_intent');
    try {
      if (!empty($intent_id)) {
        $intent = PaymentIntent::retrieve($intent_id);
      }
      else {
        // If there is no payment intent, it means we are not in a checkout
        // flow with the stripe review pane, so we should assume the
        // customer is not available for SCA and create an immediate
        // off_session payment intent.
        $intent_attributes = [
          'confirm'        => TRUE,
          'off_session'    => TRUE,
          'capture_method' => $capture ? 'automatic' : 'manual',
        ];
        $intent = $this->createPaymentIntent($order, $intent_attributes, $payment);
      }
      if ($intent->status === PaymentIntent::STATUS_REQUIRES_CONFIRMATION) {
        $intent = $intent->confirm();
      }
      if ($intent->status === PaymentIntent::STATUS_REQUIRES_ACTION) {
        throw SoftDeclineException::createForPayment($payment, 'The payment intent requires action by the customer for authentication');
      }
      if (!in_array($intent->status, [PaymentIntent::STATUS_REQUIRES_CAPTURE, PaymentIntent::STATUS_SUCCEEDED], TRUE)) {
        $order->set('payment_method', NULL);
        $this->deletePaymentMethod($payment_method);
        if ($intent->status === PaymentIntent::STATUS_CANCELED) {
          $order->setData('stripe_intent', NULL);
        }

        if (is_object($intent->last_payment_error)) {
          $error = $intent->last_payment_error;
          $decline_message = sprintf('%s: %s', $error->type, $error->message ?? '');
        }
        else {
          $decline_message = $intent->last_payment_error;
        }
        throw HardDeclineException::createForPayment($payment, $decline_message);
      }
      if (count($intent->charges->data) === 0) {
        throw HardDeclineException::createForPayment($payment, sprintf('The payment intent %s did not have a charge object.', $intent->id));
      }
      $next_state = IntentHelper::getCapture($intent) ? 'completed' : 'authorization';
      $payment->setState($next_state);
      $payment->setRemoteId($intent->id);
      if ($payment_intent_price = IntentHelper::getPrice($intent)) {
        $payment->setAmount($payment_intent_price);
      }
      $payment->save();

      $metadata = $intent->metadata->toArray();
      $event = new PaymentIntentUpdateEvent($order, $metadata, $payment);
      $this->eventDispatcher->dispatch($event, StripeEvents::PAYMENT_INTENT_UPDATE);
      $metadata += $event->getMetadata();
      // If there are no updates, then no need to waste resources on the call.
      if (!empty($metadata)) {
        PaymentIntent::update($intent->id, [
          'metadata' => $metadata,
        ]);
      }

      $order->unsetData('stripe_intent');
      $order->save();
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e, $payment);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, ?Price $amount = NULL): void {
    $this->assertPaymentState($payment, ['authorization']);
    // If not specified, capture the entire amount.
    $amount = $amount ?: $payment->getAmount();

    try {
      $remote_id = $payment->getRemoteId();
      $intent = NULL;
      if (str_starts_with($remote_id, "pi_")) {
        $intent = PaymentIntent::retrieve($remote_id);
        $intent_id = $intent->id;
        $charge = Charge::retrieve($intent['charges']['data'][0]->id);
      }
      else {
        $charge = Charge::retrieve($remote_id);
        $intent_id = $charge->payment_intent;
      }

      $amount_to_capture = $this->minorUnitsConverter->toMinorUnits($amount);
      if (!empty($intent_id)) {
        if (empty($intent)) {
          $intent = PaymentIntent::retrieve($intent_id);
        }
        if ($intent->status === PaymentIntent::STATUS_REQUIRES_CAPTURE) {
          $intent->capture(['amount_to_capture' => $amount_to_capture]);
        }
        if ($intent->status === PaymentIntent::STATUS_SUCCEEDED) {
          $payment->setState('completed');
          $payment->save();
        }
        else {
          throw PaymentGatewayException::createForPayment($payment, 'Only requires_capture PaymentIntents can be captured.');
        }
      }
      else {
        $charge->amount = $amount_to_capture;
        $transaction_data = [
          'amount' => $charge->amount,
        ];
        $charge->capture($transaction_data);
      }
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e, $payment);
    }

    $payment->setState('completed');
    $payment->setAmount($amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment): void {
    $this->assertPaymentState($payment, ['authorization']);
    // Void Stripe payment - release uncaptured payment.
    try {
      $remote_id = $payment->getRemoteId();
      $intent = NULL;
      if (str_starts_with($remote_id, "pi_")) {
        $intent = PaymentIntent::retrieve($remote_id);
        $intent_id = $intent->id;
      }
      else {
        $charge = Charge::retrieve($remote_id);
        $intent_id = $charge->payment_intent;
      }

      if (!empty($intent_id)) {
        if (empty($intent)) {
          $intent = PaymentIntent::retrieve($intent_id);
        }
        $statuses_to_void = [
          PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD,
          PaymentIntent::STATUS_REQUIRES_CAPTURE,
          PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
          PaymentIntent::STATUS_REQUIRES_ACTION,
        ];
        if (!in_array($intent->status, $statuses_to_void, TRUE)) {
          throw PaymentGatewayException::createForPayment($payment, 'The PaymentIntent cannot be voided.');
        }
        $intent->cancel();
        $data['payment_intent'] = $intent->id;
      }
      else {
        $data = [
          'charge' => $remote_id,
        ];
        // Voiding an authorized payment is done by creating a refund.
        $release_refund = Refund::create($data);
        ErrorHelper::handleErrors($release_refund, $payment);
      }
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e, $payment);
    }

    $payment->setState('authorization_voided');
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, ?Price $amount = NULL): void {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);

    try {
      $remote_id = $payment->getRemoteId();
      $minor_units_amount = $this->minorUnitsConverter->toMinorUnits($amount);
      $data = ['amount' => $minor_units_amount];

      if (str_starts_with($remote_id, "pi_")) {
        $data['payment_intent'] = $remote_id;
      }
      else {
        $data['charge'] = $remote_id;
      }

      $refund = Refund::create($data, [
        'idempotency_key' => $this->uuidService->generate(),
      ]);
      ErrorHelper::handleErrors($refund, $payment);
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e, $payment);
    }

    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($amount);
    if ($new_refunded_amount->lessThan($payment->getAmount())) {
      $payment->setState('partially_refunded');
    }
    else {
      $payment->setState('refunded');
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details): void {
    $required_keys = [
      // The expected keys are payment gateway specific and usually match
      // the PaymentMethodAddForm form elements. They are expected to be valid.
      'stripe_payment_method_id',
    ];
    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw InvalidRequestException::createForPayment($payment_method, sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    $this->doCreatePaymentMethod($payment_method, $payment_details);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method): void {
    // Delete the remote record.
    $payment_method_remote_id = $payment_method->getRemoteId();
    try {
      $remote_payment_method = PaymentMethod::retrieve($payment_method_remote_id);
      if ($remote_payment_method->customer) {
        $remote_payment_method->detach();
      }
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e, $payment_method);
    }
    $payment_method->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentIntent(OrderInterface $order, array $intent_attributes = [], ?PaymentInterface $payment = NULL): ?PaymentIntent {
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $payment ? $payment->getPaymentMethod() : $order->get('payment_method')->entity;
    $amount = $payment ? $payment->getAmount() : $order->getBalance();

    $default_intent_attributes = [
      'amount' => $this->minorUnitsConverter->toMinorUnits($amount),
      'currency' => strtolower($amount->getCurrencyCode()),
      'payment_method_types' => ['card'],
      'metadata' => [
        'order_id' => $order->id(),
        'store_id' => $order->getStoreId(),
      ],
      'payment_method' => $payment_method->getRemoteId(),
      'capture_method' => 'automatic',
    ];

    $customer_remote_id = $this->getRemoteCustomerId($order->getCustomer());
    if (!empty($customer_remote_id)) {
      $default_intent_attributes['customer'] = $customer_remote_id;
    }

    $intent_array = NestedArray::mergeDeep($default_intent_attributes, $intent_attributes);

    // Add metadata and extra transaction data where required.
    $event = new PaymentIntentCreateEvent($order, $intent_array);
    $this->eventDispatcher->dispatch($event, StripeEvents::PAYMENT_INTENT_CREATE);

    // Alter or extend the intent array from additional information added
    // through the event.
    $intent_array = $event->getIntentAttributes();

    try {
      $intent = PaymentIntent::create($intent_array);
      $order->setData('stripe_intent', $intent->id)->save();
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e, $payment);
    }
    return $intent;
  }

  /**
   * Creates the payment method on the gateway.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return \Stripe\StripeObject
   *   The payment method information returned by the gateway. Notable keys:
   *   - token: The remote ID.
   *   Credit card specific keys:
   *   - card_type: The card type.
   *   - last4: The last 4 digits of the credit card number.
   *   - expiration_month: The expiration month.
   *   - expiration_year: The expiration year.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function doCreatePaymentMethod(PaymentMethodInterface $payment_method, array $payment_details): StripeObject {
    $stripe_payment_method_id = $payment_details['stripe_payment_method_id'];
    $owner = $payment_method->getOwner();
    $customer_id = NULL;
    if ($owner && $owner->isAuthenticated()) {
      $customer_id = $this->getRemoteCustomerId($owner);
    }
    try {
      $stripe_payment_method = PaymentMethod::retrieve($stripe_payment_method_id);

      // Update payment method with information from Stripe.
      $payment_method->set('card_type', $this->mapCreditCardType($stripe_payment_method->card['brand']));
      $payment_method->set('card_number', $stripe_payment_method->card['last4']);
      $payment_method->set('card_exp_month', $stripe_payment_method->card['exp_month']);
      $payment_method->set('card_exp_year', $stripe_payment_method->card['exp_year']);
      $payment_method->setRemoteId($payment_details['stripe_payment_method_id']);
      $expires = CreditCard::calculateExpirationTimestamp($stripe_payment_method->card['exp_month'], $stripe_payment_method->card['exp_year']);
      $payment_method->setExpiresTime($expires);

      if ($customer_id) {
        $stripe_payment_method->attach(['customer' => $customer_id]);
        $email = $owner->getEmail();
      }
      // If the user is authenticated, created a Stripe customer to attach the
      // payment method to.
      elseif ($owner && $owner->isAuthenticated()) {
        $email = $owner->getEmail();
        $customer = Customer::create([
          'email' => $email,
          'name' => $owner->getDisplayName(),
          'description' => $this->t('Customer for :mail', [':mail' => $email]),
          'payment_method' => $stripe_payment_method_id,
        ]);
        $customer_id = $customer->id;
        $this->setRemoteCustomerId($owner, $customer_id);
        $owner->save();
      }
      else {
        $email = NULL;
      }

      if ($customer_id && $email) {
        $payment_method_data = [
          'email' => $email,
        ];
        $billing_profile = $payment_method->getBillingProfile();
        $formatted_address = $billing_profile ? $this->getFormattedAddress($billing_profile) : NULL;
        if (!empty($formatted_address)) {
          $payment_method_data = array_merge($payment_method_data, $formatted_address);
        }
        PaymentMethod::update($stripe_payment_method_id, ['billing_details' => $payment_method_data]);
      }
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e, $payment_method);
    }
    return $stripe_payment_method->card;
  }

  /**
   * Maps the Stripe credit card type to a Commerce credit card type.
   *
   * @param string $card_type
   *   The Stripe credit card type.
   *
   * @return string
   *   The Commerce credit card type.
   */
  protected function mapCreditCardType(string $card_type): string {
    $map = [
      'amex' => 'amex',
      'diners' => 'dinersclub',
      'discover' => 'discover',
      'jcb' => 'jcb',
      'mastercard' => 'mastercard',
      'visa' => 'visa',
      'unionpay' => 'unionpay',
    ];
    if (!isset($map[$card_type])) {
      throw new HardDeclineException(sprintf('Unsupported credit card type "%s".', $card_type));
    }

    return $map[$card_type];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormattedAddress(ProfileInterface $profile, $type = 'billing'): ?array {
    if ($profile->get('address')->isEmpty()) {
      return NULL;
    }

    $formatted_address = [];
    /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $address */
    $address = $profile->get('address')->first();
    // Format the name.
    $full_name = array_filter([
      $address->getGivenName(),
      $address->getFamilyName(),
    ]);
    if (!empty($full_name)) {
      $formatted_address['name'] = implode(' ', $full_name);
    }
    // Convert the address to Stripe format.
    $address_parts = array_filter([
      'city' => $address->getLocality(),
      'country' => $address->getCountryCode(),
      'line1' => $address->getAddressLine1(),
      'line2' => $address->getAddressLine2(),
      'postal_code' => $address->getPostalCode(),
      'state' => $address->getAdministrativeArea(),
    ]);
    if (!empty($address_parts)) {
      $formatted_address['address'] = $address_parts;
      // The name field is required if an address is provided.
      if ($type === 'shipping' && !isset($formatted_address['name'])) {
        $formatted_address['name'] = '';
      }
    }

    return !empty($formatted_address) ? $formatted_address : NULL;
  }

}
