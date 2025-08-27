<?php

namespace Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway;

use Drupal\advancedqueue\Job;
use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Exception\InvalidRequestException;
use Drupal\commerce_payment\Exception\PaymentGatewayException;
use Drupal\commerce_payment\Exception\SoftDeclineException;
use Drupal\commerce_payment\PaymentMethodTypeManager;
use Drupal\commerce_payment\PaymentOrderUpdaterInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OffsitePaymentGatewayBase;
use Drupal\commerce_price\Price;
use Drupal\commerce_stripe\ErrorHelper;
use Drupal\commerce_stripe\Event\PaymentIntentCreateEvent;
use Drupal\commerce_stripe\Event\PaymentIntentUpdateEvent;
use Drupal\commerce_stripe\Event\StripeEvents;
use Drupal\commerce_stripe\IntentHelper;
use Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType\StripePaymentMethodTypeInterface;
use Drupal\commerce_stripe\WebhookEventState;
use Drupal\commerce_stripe_webhook_event\WebhookEvent;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\profile\Entity\ProfileInterface;
use Stripe\ApiRequestor;
use Stripe\Balance;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Event;
use Stripe\Exception\ApiErrorException;
use Stripe\HttpClient\CurlClient;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Stripe\Refund;
use Stripe\SetupIntent;
use Stripe\Stripe as StripeLibrary;
use Stripe\Webhook;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides the Stripe Payment Element payment gateway.
 *
 * @CommercePaymentGateway(
 *   id = "stripe_payment_element",
 *   label = "Stripe Payment Element",
 *   display_label = "Stripe Payment Element",
 *   payment_method_types = {"stripe_card"},
 *   forms = {
 *     "offsite-payment" = "Drupal\commerce_stripe\PluginForm\OffsiteRedirect\PaymentOffsiteForm",
 *   },
 *   credit_card_types = {
 *     "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard",
 *   "visa", "unionpay"
 *   },
 *   requires_billing_information = FALSE,
 * )
 */
class StripePaymentElement extends OffsitePaymentGatewayBase implements StripePaymentElementInterface {

  /**
   * Payment source for use in payment intent metadata.
   */
  public const PAYMENT_SOURCE = 'Drupal';

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
   * The logging channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected LoggerChannel $logger;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected UuidInterface $uuidService;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Whether the Webhook Event Submodule has been installed.
   *
   * @var bool
   */
  protected bool $webhookEventModuleIsInstalled;

  /**
   * The payment method type manager service.
   *
   * @var \Drupal\commerce_payment\PaymentMethodTypeManager
   */
  protected PaymentMethodTypeManager $paymentMethodTypeManager;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * The payment order updater service.
   *
   * @var \Drupal\commerce_payment\PaymentOrderUpdaterInterface
   */
  protected PaymentOrderUpdaterInterface $paymentOrderUpdater;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $instance->eventDispatcher = $container->get('event_dispatcher');
    $instance->moduleExtensionList = $container->get('extension.list.module');
    $instance->logger = $container->get('logger.channel.commerce_stripe');
    $instance->uuidService = $container->get('uuid');
    $instance->renderer = $container->get('renderer');
    $instance->currentUser = $container->get('current_user');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->paymentMethodTypeManager = $container->get('plugin.manager.commerce_payment_method_type');
    $instance->configFactory = $container->get('config.factory');
    $instance->queueFactory = $container->get('queue');
    $instance->paymentOrderUpdater = $container->get('commerce_payment.order_updater');

    $instance->init();
    $instance->webhookEventModuleIsInstalled = $instance->moduleHandler->moduleExists('commerce_stripe_webhook_event');

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
    StripeLibrary::setAppInfo('Drupal Commerce by Centarro', $version, 'https://www.drupal.org/project/commerce_stripe', 'pp_partner_Fa3jTqCJqTDtHD');

    // If Drupal is configured to use a proxy for outgoing requests, make sure
    // that the proxy CURLOPT_PROXY setting is passed to the Stripe SDK client.
    $http_client_config = Settings::get('http_client_config');
    if (!empty($http_client_config['proxy']['https'])) {
      $curl = new CurlClient([CURLOPT_PROXY => $http_client_config['proxy']['https']]);
      ApiRequestor::setHttpClient($curl);
    }

    StripeLibrary::setApiVersion($this->getApiVersion());
    if (!empty($this->configuration['access_token'])) {
      StripeLibrary::setApiKey($this->configuration['access_token']);
    }
    else {
      StripeLibrary::setApiKey($this->getSecretKey());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'api_version' => NULL,
      'authentication_method' => 'stripe_connect',
      'access_token' => '',
      'stripe_user_id' => '',
      'publishable_key' => '',
      'secret_key' => '',
      'webhook_signing_secret' => '',
      'payment_method_usage' => 'on_session',
      'capture_method' => 'automatic',
      'style' => [],
      'checkout_form_display_label' => [],
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
      '#title' => $this->t('Publishable key'),
      '#default_value' => $this->getPublishableKey(),
      '#states' => $stripe_connect_states,
    ];
    $form['secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret key'),
      '#default_value' => $this->getSecretKey(),
      '#states' => $stripe_connect_states,
    ];
    $form['validate_api_keys'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Validate API keys upon form submission.'),
      '#states' => $stripe_connect_states,
      '#default_value' => TRUE,
    ];
    $form['webhook'] = [
      '#type' => 'fieldset',
      '#title' => 'Webhooks',
    ];
    $form['webhook']['webhook_signing_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Webhook signing secret'),
      '#default_value' => $this->getWebhookSigningSecret(),
      '#description' => $this->t('The signing secret for your <a href="https://dashboard.stripe.com/webhooks" target="_blank">webhook</a>.'),
      '#required' => FALSE,
      '#size' => 96,
    ];
    $webhook_endpoint_url = $this->t('This will be provided after you have saved this gateway.');
    if ($id = $form_state->getFormObject()->getEntity()->id()) {
      $webhook_endpoint_url = Url::fromRoute('commerce_payment.notify', ['commerce_payment_gateway' => $id])->setAbsolute()->toString();
    }
    $form['webhook']['webhook_endpoint_url'] = [
      '#type' => 'item',
      '#title' => $this->t('Webhook endpoint URL'),
      '#markup' => $webhook_endpoint_url,
      '#description' => $this->t('Specify this when configuring your Webhook in Stripe.'),
    ];
    $form['payment_method_usage'] = [
      '#type' => 'radios',
      '#title' => $this->t('Payment method usage'),
      '#options' => [
        'on_session' => $this->t('On-session: the customer will always initiate payments in checkout'),
        'off_session' => $this->t("Off-session or mixed: the site may process payments on the customer's behalf (e.g., recurring billing)"),
        'single_use' => $this->t('Single use: the payment method will not be made available for subsequent transactions'),
      ],
      '#empty_value' => '',
      '#description' => $this->t('This value will be passed as the setup_future_usage parameter in your payment intents.'),
      '#default_value' => $this->getPaymentMethodUsage(),
    ];
    $form['capture_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Capture method'),
      '#options' => [
        'automatic_async' => $this->t('Automatic Async: Stripe automatically captures funds when the customer authorizes the payment. Recommended over "automatic" due to improved latency.'),
        'automatic' => $this->t('Automatic: Stripe automatically captures funds when the customer authorizes the payment.'),
        'manual' => $this->t('Manual: Place a hold on the funds when the customer authorizes the payment, but don’t capture the funds until later. (Not all payment methods support this.)'),
      ],
      '#empty_value' => '',
      '#description' => $this->t('This value will be passed as the capture_method parameter in your intents.'),
      '#default_value' => $this->getCaptureMethod(),
    ];
    $form['style'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Style settings'),
      '#description' => $this->t('Preview the options in the <a href=":url1" target="_blank">Payment Element</a> homepage or read more in the <a href=":url2" target="_blank">Elements Appearance API</a> documentation.', [
        ':url1' => 'https://stripe.com/docs/payments/payment-element',
        ':url2' => 'https://stripe.com/docs/elements/appearance-api',
      ]),
    ];
    $form['style']['theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Theme'),
      '#options' => [
        'stripe' => $this->t('Stripe'),
        'night' => $this->t('Night'),
        'flat' => $this->t('Flat'),
      ],
      '#default_value' => $this->configuration['style']['theme'] ?? 'stripe',
    ];
    $form['style']['layout'] = [
      '#type' => 'select',
      '#title' => $this->t('Layout'),
      '#options' => [
        'tabs' => $this->t('Tabs'),
        'accordion' => $this->t('Accordion'),
      ],
      '#default_value' => $this->configuration['style']['layout'] ?? 'tabs',
    ];
    $form['checkout_form_display_label'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Checkout form display label'),
    ];
    $form['checkout_form_display_label']['custom_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom display label'),
      '#description' => $this->t('Defaults to <em>Credit card</em>. Use a space character to show no text label beside the logos.'),
      '#default_value' => $this->configuration['checkout_form_display_label']['custom_label'] ?? '',
    ];
    $form['checkout_form_display_label']['show_payment_method_logos'] = [
      '#type' => 'select',
      '#title' => $this->t('Show payment method logos?'),
      '#options' => [
        'no' => $this->t('No'),
        'after' => $this->t('After the label'),
        'before' => $this->t('Before the label'),
      ],
      '#default_value' => $this->configuration['checkout_form_display_label']['show_payment_method_logos'] ?? 'no',
    ];
    $default_logos = $this->configuration['checkout_form_display_label']['include_logos'] ?? [
      'visa',
      'mastercard',
      'amex',
      'discover',
    ];
    $payment_method_types = $this->getOurPaymentMethodTypes();
    $supported_logos = [];
    foreach ($payment_method_types as $payment_method_type) {
      foreach ($payment_method_type->getLogos() as $logo_id => $logo_label) {
        $supported_logos[$logo_id] = $logo_label;
      }
    }
    $default_logos = array_intersect($default_logos, array_keys($supported_logos));
    $form['checkout_form_display_label']['include_logos'] = [
      '#title' => $this->t('Logos to include'),
      '#type' => 'checkboxes',
      '#options' => $supported_logos,
      '#default_value' => $default_logos,
      '#states' => [
        'invisible' => [
          ':input[name="configuration[' . $this->pluginId . '][checkout_form_display_label][show_payment_method_logos]"]' => ['value' => 'no'],
        ],
      ],
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
      if ($values['authentication_method'] === 'api_keys') {
        $this->configuration['secret_key'] = $values['secret_key'];
      }
      else {
        $this->configuration['access_token'] = $values['access_token'];
        $this->configuration['stripe_user_id'] = $values['stripe_user_id'];
      }
      $this->configuration['authentication_method'] = $values['authentication_method'];
      $this->configuration['webhook_signing_secret'] = $values['webhook']['webhook_signing_secret'];
      $this->configuration['payment_method_usage'] = $values['payment_method_usage'];
      $this->configuration['capture_method'] = $values['capture_method'];
      $this->configuration['style'] = $values['style'];
      $this->configuration['checkout_form_display_label'] = $values['checkout_form_display_label'];
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
        $intent = $this->getStripePaymentIntent($intent_id);
      }
      else {
        // If there is no payment intent, it means we are not in a checkout
        // flow with the stripe review pane, so we should assume the
        // customer is not available for SCA and create an immediate
        // off_session payment intent.
        $intent_attributes = [
          'confirm'        => TRUE,
          'off_session'    => TRUE,
        ];
        $intent = $this->createPaymentIntent($order, $intent_attributes, $payment);
      }
      if ($intent === NULL) {
        throw SoftDeclineException::createForPayment($payment, 'The intent is missing');
      }
      if ($intent->status === PaymentIntent::STATUS_REQUIRES_CONFIRMATION) {
        $intent = $intent->confirm();
      }
      if ($intent->status === PaymentIntent::STATUS_REQUIRES_ACTION) {
        throw SoftDeclineException::createForPayment($payment, 'The payment intent requires action by the customer for authentication');
      }
      if ($intent->status === PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD) {
        throw SoftDeclineException::createForPayment($payment, 'The payment intent requires payment method');
      }
      if (!in_array($intent->status, [
        PaymentIntent::STATUS_REQUIRES_CAPTURE,
        PaymentIntent::STATUS_PROCESSING,
        PaymentIntent::STATUS_SUCCEEDED,
      ], TRUE)) {
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
      if (empty($intent->latest_charge) && count($intent->charges->data) === 0) {
        throw HardDeclineException::createForPayment($payment, sprintf('The payment intent %s did not have a charge object.', $intent->id));
      }
      // Keep the payment in the new status if it has not yet been processed.
      if ($intent->status !== PaymentIntent::STATUS_PROCESSING) {
        $next_state = IntentHelper::getCapture($intent) ? 'completed' : 'authorization';
        $payment->setState($next_state);
      }
      $payment->setRemoteId($intent->id);

      /** @var \Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType\StripePaymentMethodTypeInterface $payment_method_type */
      $payment_method_type = $payment_method->getType();
      $payment_method_type->updatePayment($payment, $intent);
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
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e, $payment);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Stripe\Exception\ApiErrorException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\commerce\Response\NeedsRedirectException
   */
  public function onReturn(OrderInterface $order, Request $request): void {
    // If the order should force a 500 error, throw it now. This functionality
    // can be suppressed by setting the skip_return_failure query parameter in
    // the request object before calling this function manually.
    if (empty($query['skip_return_failure'])) {
      $commerce_stripe_debug_settings = Settings::get('commerce_stripe.debug', []);
      $emails = $commerce_stripe_debug_settings['return_failure_emails'] ?? [];
      if (!empty($emails) && in_array($order->getEmail(), $emails, TRUE)) {
        throw new \RuntimeException('Failed the Payment Element return based on email.');
      }
    }

    $stripe_intent = $this->getStripeIntentFromRequest($order, $request);
    $stripe_payment_method = $this->getStripePaymentMethod($stripe_intent->payment_method);
    $this->createPaymentMethodFromStripePaymentMethod($stripe_payment_method, $order);
    if ($stripe_intent instanceof PaymentIntent) {
      $this->handlePaymentIntent($order, $stripe_intent);
    }
    else {
      $this->handleSetupIntent($order, $stripe_intent);
    }
    // Indicate how the order was placed.
    $order->setData('order_placed_source', [
      'type' => 'return',
    ]);
    $step_id = $this->placeOrder($order);
    throw new NeedsRedirectException(Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $order->id(),
      'step' => $step_id,
    ])->toString());
  }

  /**
   * React to the payment intent returned in the onReturn callback.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Stripe\PaymentIntent $payment_intent
   *   The stripe payment intent.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function handlePaymentIntent(OrderInterface $order, PaymentIntent $payment_intent): void {
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $payment_storage->create([
      'state' => 'new',
      'amount' => IntentHelper::getPrice($payment_intent) ?? $order->getBalance(),
      'payment_gateway' => $this->parentEntity->id(),
      'order_id' => $order->id(),
      'payment_method' => $order->get('payment_method')->getString(),
    ]);
    $this->createPayment($payment);
  }

  /**
   * React to the setup intent returned in the onReturn callback.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Stripe\SetupIntent $setup_intent
   *   The stripe setup intent.
   */
  protected function handleSetupIntent(OrderInterface $order, SetupIntent $setup_intent): void {

  }

  /**
   * Create a payment for an order from a Stripe Payment Method.
   *
   * This method is used by both onReturn() and processWebHook(), so that
   * behavior is consistent. The order must be loaded with loadForUpdate()
   * to minimize the chance of a race condition where both the return
   * and the webhook are attempting to create the same payment.
   *
   * @param \Stripe\PaymentMethod $stripe_payment_method
   *   The stripe payment method.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   */
  protected function createPaymentMethodFromStripePaymentMethod(PaymentMethod $stripe_payment_method, OrderInterface $order):void {
    $payment_method_type = 'stripe_' . $stripe_payment_method->type;
    $payment_method_type_definitions = $this->getPaymentMethodTypeDefinitions();

    if (!array_key_exists($payment_method_type, $payment_method_type_definitions)) {
      throw new PaymentGatewayException(sprintf('The selected stripe payment method type(%s) is not currently supported.', $payment_method_type));
    }

    $payment_method = $order->get('payment_method')->entity;
    if ($payment_method === NULL) {
      // Create a payment method.
      $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
      $payment_method = $payment_method_storage->createForCustomer(
        $payment_method_type,
        $this->parentEntity->id(),
        $order->getCustomerId(),
        $order->getBillingProfile()
      );
    }

    $payment_details = ['stripe_payment_method' => $stripe_payment_method, 'commerce_order' => $order];
    $this->createPaymentMethod($payment_method, $payment_details);
    $order->set('payment_method', $payment_method);
  }

  /**
   * Get the Stripe payment method.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Stripe\PaymentIntent|\Stripe\SetupIntent
   *   The stripe payment method.
   *
   * @throws \Stripe\Exception\ApiErrorException
   */
  protected function getStripeIntentFromRequest(OrderInterface $order, Request $request): PaymentIntent|SetupIntent {
    $query = $request->query->all();
    $intent_id = $order->getData('stripe_intent');

    if (empty($query['payment_intent']) && empty($query['setup_intent'])) {
      throw new PaymentGatewayException('Either the payment_intent or setup_intent parameter must be returned.');
    }

    $intent = $this->getIntent(!empty($query['payment_intent']) ? $query['payment_intent'] : $query['setup_intent']);
    if ($intent === NULL || $intent->id !== $intent_id) {
      throw new PaymentGatewayException('The intent is missing or invalid.');
    }

    if ($intent instanceof PaymentIntent && !in_array($intent->status, [
      PaymentIntent::STATUS_SUCCEEDED,
      PaymentIntent::STATUS_PROCESSING,
      PaymentIntent::STATUS_REQUIRES_CAPTURE,
    ], TRUE)) {
      throw new PaymentGatewayException(sprintf('Unexpected payment intent status %s.', $intent->status));
    }
    if ($intent instanceof SetupIntent && !in_array($intent->status, [
      SetupIntent::STATUS_SUCCEEDED,
      SetupIntent::STATUS_PROCESSING,
    ], TRUE)) {
      throw new PaymentGatewayException(sprintf('Unexpected setup intent status %s.', $intent->status));
    }

    return $intent;
  }

  /**
   * Get the Stripe payment method.
   *
   * @param string $payment_method_id
   *   The payment method id.
   *
   * @return \Stripe\PaymentMethod
   *   The stripe payment method.
   */
  protected function getStripePaymentMethod(string $payment_method_id): PaymentMethod {
    try {
      $stripe_payment_method = PaymentMethod::retrieve($payment_method_id);
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e);
    }

    return $stripe_payment_method;
  }

  /**
   * Reacts to Webhook events.
   *
   * {@inheritdoc}
   *
   * @throws \Exception
   * @throws \Throwable
   */
  public function onNotify(Request $request): ?Response {
    try {
      $stripe_signature = $request->headers->get('Stripe-Signature', '');
      $payload = $request->getContent();

      $webhook_signing_secret = $this->getWebhookSigningSecret();
      if (!empty($webhook_signing_secret)) {
        $webhook_event = Webhook::constructEvent($payload, $stripe_signature, $webhook_signing_secret);
      }
      else {
        $data = Json::decode($payload);
        $webhook_event = Event::constructFrom($data);
      }

      $webhook_event_id = NULL;
      if ($this->webhookEventModuleIsInstalled) {
        $webhook_event_id = WebhookEvent::insert($request, $webhook_event, $stripe_signature);
        if ($webhook_event_id === NULL) {
          // This is an event we previously received.
          return new Response('', 200);
        }
        $settings = $this->configFactory->get('commerce_stripe_webhook_event.settings');
        $queue_webhooks = $settings->get('queue');
        $advanced_queue_installed = $this->moduleHandler->moduleExists('advancedqueue');
        if ($queue_webhooks) {
          $queue_item = [
            'payment_gateway_id' => $this->parentEntity->id(),
            'webhook_event_id' => $webhook_event_id,
          ];
          if ($advanced_queue_installed) {
            $queue_storage = $this->entityTypeManager->getStorage('advancedqueue_queue');
            /** @var \Drupal\advancedqueue\Entity\QueueInterface $queue_webhooks */
            $queue_webhooks = $queue_storage->load('commerce_stripe_webhook_event');
            $webhook_event_job = Job::create('commerce_stripe_webhook_event', $queue_item);
            // @todo We should consider making this configurable.
            $defer_time = 5;
            $webhook_event_job->setAvailableTime($this->time->getCurrentTime() + $defer_time);
            $queue_webhooks->enqueueJob($webhook_event_job);
          }
          else {
            $queue_webhooks = $this->queueFactory->get('commerce_stripe_webhook_event_processor');
            $queue_webhooks->createItem($queue_item);
          }
          return new Response('', 200);
        }
      }
      return $this->processWebHook($webhook_event_id, $webhook_event);
    }
    catch (\Throwable $exception) {
      return new Response($exception->getMessage(), 500);
    }
  }

  /**
   * Process a webhook event.
   *
   * @param int|null $webhook_event_id
   *   The webhook event id.
   * @param \Stripe\Event $webhook_event
   *   The webhook event.
   *
   * @return \Symfony\Component\HttpFoundation\Response|null
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Throwable
   */
  public function processWebHook(?int $webhook_event_id, Event $webhook_event): ?Response {
    $supported_events = [
      Event::PAYMENT_INTENT_SUCCEEDED,
      Event::PAYMENT_INTENT_PAYMENT_FAILED,
      Event::PAYMENT_INTENT_CANCELED,
      Event::CHARGE_REFUNDED,
    ];
    $webhook_event_type = $webhook_event->type ?? NULL;
    // Ignore unsupported events.
    if (!in_array($webhook_event_type, $supported_events, TRUE)) {
      $this->updateWebhookEventStatus($webhook_event_id, WebhookEventState::Skipped->value, $this->t('Event (%event) not supported', ['%event' => $webhook_event_type]));
      return NULL;
    }

    /** @var \Drupal\commerce_payment\PaymentStorageInterface $payment_storage */
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    /** @var \Stripe\ApiResource $event_object */
    $event_object = $webhook_event->data->object;

    try {
      $webhook_event_entity = NULL;
      switch ($webhook_event_type) {
        case Event::CHARGE_REFUNDED:
          /** @var \Stripe\Charge $charge */
          $charge = $event_object;
          if ($charge->refunds === NULL) {
            $charge = Charge::retrieve($charge->id);
          }
          /** @var \Stripe\Refund $latest_refund */
          $latest_refund = reset($charge->refunds->data);
          $refund_source = $latest_refund->metadata['refund_source'] ?? NULL;

          $payment = $payment_storage->loadByRemoteId($latest_refund['payment_intent']);
          $webhook_event_entity = $payment;

          // Ignore the request as it was made from Drupal.
          if ($refund_source === self::PAYMENT_SOURCE) {
            $this->updateWebhookEventStatus($webhook_event_id, WebhookEventState::Skipped->value, $this->t('Event source is Drupal'), $webhook_event_entity);
            return NULL;
          }

          // Ignore the request if amount_captured is 0.
          if (!((int) $charge->amount_captured)) {
            $this->updateWebhookEventStatus($webhook_event_id, WebhookEventState::Skipped->value, $this->t('amount_captured is 0'), $webhook_event_entity);
            return NULL;
          }

          if (!$payment) {
            $this->updateWebhookEventStatus($webhook_event_id, WebhookEventState::Skipped->value, $this->t('payment with remote id (%remote_id) does not exist', ['%remote_id' => $latest_refund['payment_intent']]), $webhook_event_entity);
            return NULL;
          }

          // Calculate the refund amount.
          $refund_amount = $this->minorUnitsConverter->fromMinorUnits(
            $charge->amount_refunded - ($charge->amount - $charge->amount_captured),
            strtoupper($charge->currency)
          );
          if ($refund_amount->lessThan($payment->getAmount())) {
            $transition_id = 'partially_refund';
          }
          else {
            $transition_id = 'refund';
          }
          if ($payment->getState()->isTransitionAllowed($transition_id)) {
            $payment->getState()->applyTransitionById($transition_id);
          }
          $payment->setRefundedAmount($refund_amount);
          $payment->save();
          break;

        case Event::PAYMENT_INTENT_CANCELED:
        case Event::PAYMENT_INTENT_PAYMENT_FAILED:
          /** @var \Stripe\PaymentIntent $payment_intent */
          $payment_intent = $event_object;
          $void_source = $payment_intent->metadata['void_source'] ?? NULL;

          $payment = $payment_storage->loadByRemoteId($payment_intent->id);
          $webhook_event_entity = $payment;

          // Ignore the request as it was made from Drupal.
          if ($void_source === self::PAYMENT_SOURCE) {
            $this->updateWebhookEventStatus($webhook_event_id, WebhookEventState::Skipped->value, $this->t('Event source is Drupal'), $webhook_event_entity);
            return NULL;
          }

          if (!$payment) {
            $this->updateWebhookEventStatus($webhook_event_id, WebhookEventState::Skipped->value, $this->t('payment with remote id (%remote_id) does not exist', ['%remote_id' => $payment_intent->id]), $webhook_event_entity);
            return NULL;
          }

          // Void the payment if the authorization has been voided.
          if ($payment->getState()->isTransitionAllowed('void')) {
            $payment->getState()->applyTransitionById('void');
            $payment->save();
          }
          break;

        case Event::PAYMENT_INTENT_SUCCEEDED:
          /** @var \Stripe\PaymentIntent $payment_intent */
          $payment_intent = $event_object;

          $payment = $payment_storage->loadByRemoteId($payment_intent->id);
          $webhook_event_entity = $payment;

          if (!$payment) {
            $order_id = $payment_intent->metadata['order_id'] ?? NULL;
            if (empty($order_id)) {
              $this->updateWebhookEventStatus($webhook_event_id, WebhookEventState::Skipped->value, $this->t('order_id not present in metadata'), $webhook_event_entity);
              return NULL;
            }
            /** @var \Drupal\commerce_order\OrderStorageInterface $order_storage */
            $order_storage = $this->entityTypeManager->getStorage('commerce_order');
            $order = $order_storage->loadForUpdate($order_id);
            if ($order === NULL) {
              $this->updateWebhookEventStatus($webhook_event_id, WebhookEventState::Skipped->value, $this->t('order with order id (%order_id) does not exist', ['%order_id' => $order_id]), $webhook_event_entity);
              return NULL;
            }
            if ($order->getState()->getId() === 'draft') {
              $stripe_payment_method = PaymentMethod::retrieve($payment_intent->payment_method);
              $this->createPaymentMethodFromStripePaymentMethod($stripe_payment_method, $order);
              $this->handlePaymentIntent($order, $payment_intent);
              // Indicate how the order was placed.
              $order->setData('order_placed_source', [
                'type' => 'notify',
                'commerce_stripe_webhook_event' => [
                  'type' => $webhook_event_type,
                  'id' => $webhook_event_id,
                ],
              ]);
              $this->placeOrder($order);
              $this->updateWebhookEventStatus($webhook_event_id, WebhookEventState::Succeeded->value, NULL, $webhook_event_entity);
              return NULL;
            }
            $order_storage->releaseLock($order_id);
            $this->updateWebhookEventStatus($webhook_event_id, WebhookEventState::Skipped->value, $this->t('Payment Intent has already been processed'), $webhook_event_entity);
            return NULL;
          }

          // Complete the payment if authorization is captured.
          if ($payment->getState()->getId() === 'authorization') {
            $amount = $this->minorUnitsConverter->fromMinorUnits(
              $payment_intent->amount_received,
              strtoupper($payment_intent->currency)
            );
            $payment->setAmount($amount);
            $payment->getState()->applyTransitionById('capture');
            $payment->save();
          }

          // Complete the payment that was in processing status.
          if ($payment->getState()->getId() === 'new') {
            $amount = $this->minorUnitsConverter->fromMinorUnits(
              $payment_intent->amount_received,
              strtoupper($payment_intent->currency)
            );
            $payment->setAmount($amount);
            $payment->getState()->applyTransitionById('authorize_capture');
            $payment->save();
          }
          break;
      }
    }
    catch (\Throwable $throwable) {
      $this->updateWebhookEventStatus($webhook_event_id, WebhookEventState::Failed->value, $throwable->getMessage(), $webhook_event_entity);
      throw $throwable;
    }
    $this->updateWebhookEventStatus($webhook_event_id, WebhookEventState::Succeeded->value, NULL, $webhook_event_entity);
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, ?Price $amount = NULL): void {
    $this->assertPaymentState($payment, ['authorization']);
    // If not specified, capture the entire amount.
    $amount = $amount ?: $payment->getAmount();

    try {
      $intent_id = $payment->getRemoteId();
      $intent = $this->getStripePaymentIntent($intent_id);

      $amount_to_capture = $this->minorUnitsConverter->toMinorUnits($amount);

      if ($intent->status === PaymentIntent::STATUS_REQUIRES_CAPTURE) {
        $intent = $intent->capture([
          'amount_to_capture' => $amount_to_capture,
          'metadata' => [
            'capture_source' => self::PAYMENT_SOURCE,
            'capture_uid' => $this->currentUser->id(),
          ],
        ]);
      }

      if ($intent->status === PaymentIntent::STATUS_SUCCEEDED) {
        // Log a warning to the watchdog if the amount received was unexpected.
        if ($intent->amount_received != $amount_to_capture) {
          // Set the payment amount to what was actually received.
          $received = $this->minorUnitsConverter->fromMinorUnits($intent->amount_received, $amount->getCurrencyCode());
          $payment->setAmount($received);

          $this->logger->warning($this->t('Attempted to capture @amount but received @received.', [
            '@amount' => (string) $amount,
            '@received' => (string) $received,
          ]));
        }
        else {
          $payment->setAmount($amount);
        }

        $payment->setState('completed');
        $payment->save();
      }
      else {
        throw PaymentGatewayException::createForPayment($payment, 'Only requires_capture PaymentIntents can be captured.');
      }
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e, $payment);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment): void {
    $this->assertPaymentState($payment, ['authorization']);
    // Void Stripe payment - release uncaptured payment.
    try {
      $intent_id = $payment->getRemoteId();
      $intent = $this->getStripePaymentIntent($intent_id);

      $statuses_to_void = [
        PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD,
        PaymentIntent::STATUS_REQUIRES_CAPTURE,
        PaymentIntent::STATUS_REQUIRES_CONFIRMATION,
        PaymentIntent::STATUS_REQUIRES_ACTION,
      ];
      if (!in_array($intent->status, $statuses_to_void, TRUE)) {
        throw PaymentGatewayException::createForPayment($payment, 'The PaymentIntent cannot be voided.');
      }
      $intent = PaymentIntent::update($intent->id, [
        'metadata' => [
          'void_source' => self::PAYMENT_SOURCE,
          'void_uid' => $this->currentUser->id(),
        ],
      ]);
      $intent->cancel();

      $payment->setState('authorization_voided');
      $payment->save();
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e, $payment);
    }
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
      $intent_id = $payment->getRemoteId();

      $data = [
        'amount' => $this->minorUnitsConverter->toMinorUnits($amount),
        'payment_intent' => $intent_id,
        'metadata' => [
          'refund_source' => self::PAYMENT_SOURCE,
          'refund_uid' => $this->currentUser->id(),
        ],
      ];

      $refund = Refund::create($data, [
        'idempotency_key' => $this->uuidService->generate(),
      ]);
      ErrorHelper::handleErrors($refund, $payment);

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
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e, $payment);
    }
  }

  /**
   * Creates a payment method with the given payment details.
   *
   * See onReturn() for more details.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details provided by the payment method form
   *   for on-site gateways, or the incoming request for off-site gateways.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details): void {
    // Handle previous code that passed just the stripe_payment_method_id.
    if (!array_key_exists('stripe_payment_method', $payment_details) && array_key_exists('stripe_payment_method_id', $payment_details)) {
      $payment_details['stripe_payment_method'] = PaymentMethod::retrieve($payment_details['stripe_payment_method_id']);
    }
    $required_keys = [
      // The expected keys are payment gateway specific and usually match
      // the PaymentMethodAddForm form elements. They are expected to be valid.
      'stripe_payment_method',
    ];
    foreach ($required_keys as $required_key) {
      if (!array_key_exists($required_key, $payment_details)) {
        throw InvalidRequestException::createForPayment($payment_method, sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    $stripe_payment_method = $this->attachCustomerToStripePaymentMethod($payment_method, $payment_details);

    $payment_method_type = $payment_method->getType();
    if (!$payment_method_type instanceof StripePaymentMethodTypeInterface) {
      throw PaymentGatewayException::createForPayment($payment_method, $this->t('The stripe payment method type(@payment_method_type) is not currently supported.', ['@payment_method_type' => $stripe_payment_method->type]));
    }
    $payment_method->setReusable($payment_method_type->isReusable());
    $payment_method_type->updatePaymentMethod($payment_method, $stripe_payment_method);

    $payment_method->setRemoteId($stripe_payment_method->id);
    if (!$this->isReusable()) {
      $payment_method->setReusable(FALSE);
    }
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
   * Attach the payment method to the customer.
   *
   * The stripe payment method is already created at this point.
   *
   * If it is reusable, we will attach it to the stripe customer.
   *
   * We'll create a stripe customer, if one does not yet exist.
   *
   * We can't attach payment methods that are not reusable.
   *
   * @param \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method
   *   The payment method.
   * @param array $payment_details
   *   The gateway-specific payment details.
   *
   * @return \Stripe\PaymentMethod
   *   The stripe payment method.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Stripe\Exception\ApiErrorException
   */
  public function attachCustomerToStripePaymentMethod(PaymentMethodInterface $payment_method, array $payment_details): PaymentMethod {
    /** @var \Stripe\PaymentMethod $stripe_payment_method */
    $stripe_payment_method = $payment_details['stripe_payment_method'];
    $owner = $payment_method->getOwner();
    $customer_id = NULL;
    /** @var \Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType\StripePaymentMethodTypeInterface $payment_method_type */
    $payment_method_type = $payment_method->getType();
    $is_reusable = $this->isReusable() && $payment_method_type->isReusable();
    if ($is_reusable) {
      $order = $payment_details['commerce_order'] ?? NULL;
      if ($order && $intent_id = $order->getData('stripe_intent')) {
        $intent = $this->getIntent($intent_id);
        if (($intent instanceof PaymentIntent) && (!$intent->setup_future_usage)) {
          $is_reusable = FALSE;
        }
      }
    }
    if ($owner && $owner->isAuthenticated()) {
      $customer_id = $this->getRemoteCustomerId($owner);
    }
    try {
      if ($customer_id) {
        if ($is_reusable) {
          // We cannot attach if the payment is not reusable.
          $stripe_payment_method->attach(['customer' => $customer_id]);
        }
        $email = $owner->getEmail();
      }
      // If the user is authenticated, created a Stripe customer to attach the
      // payment method to.
      elseif ($owner && $owner->isAuthenticated()) {
        $email = $owner->getEmail();
        if (empty($stripe_payment_method->customer)) {
          $customer_data = [
            'email' => $email,
            'name' => $owner->getDisplayName(),
            'description' => $this->t('Customer for :mail', [':mail' => $email]),
          ];
          if ($is_reusable) {
            $customer_data['payment_method'] = $stripe_payment_method->id;
          }
          $customer = Customer::create($customer_data);
          $customer_id = $customer->id;
        }
        else {
          $customer_id = $stripe_payment_method->customer;
        }
        $this->setRemoteCustomerId($owner, $customer_id);
        $owner->save();
      }
      else {
        $email = NULL;
      }

      if ($is_reusable && $customer_id && $email) {
        $payment_method_data = [
          'email' => $email,
        ];
        $billing_profile = $payment_method->getBillingProfile();
        $formatted_address = $billing_profile ? $this->getFormattedAddress($billing_profile) : NULL;
        if (!empty($formatted_address)) {
          $payment_method_data = array_merge($payment_method_data, $formatted_address);
        }
        PaymentMethod::update($stripe_payment_method->id, ['billing_details' => $payment_method_data]);
      }
    }
    catch (ApiErrorException $e) {
      ErrorHelper::handleException($e, $payment_method);
    }
    return $stripe_payment_method;
  }

  /**
   * {@inheritdoc}
   */
  public function createIntent(OrderInterface $order): PaymentIntent|SetupIntent {
    return $this->createPaymentIntent($order);
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentIntent(OrderInterface $order, $intent_attributes = [], ?PaymentInterface $payment = NULL): PaymentIntent {
    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $payment ? $payment->getPaymentMethod() : $order->get('payment_method')->entity;
    /** @var \Drupal\commerce_price\Price $amount */
    $amount = $payment ? $payment->getAmount() : $order->getBalance();

    $default_intent_attributes = [
      'amount' => $this->minorUnitsConverter->toMinorUnits($amount),
      'currency' => strtolower($amount->getCurrencyCode()),
      'metadata' => [
        'order_id' => $order->id(),
        'store_id' => $order->getStoreId(),
      ],
      'capture_method' => $this->getCaptureMethod(),
      'automatic_payment_methods' => [
        'enabled' => TRUE,
      ],
    ];

    $profiles = $order->collectProfiles();
    $formatted_address = isset($profiles['shipping']) ? $this->getFormattedAddress($profiles['shipping'], 'shipping') : NULL;
    if (!empty($formatted_address)) {
      $default_intent_attributes['shipping'] = $formatted_address;
    }

    $customer_remote_id = $this->getRemoteCustomerId($order->getCustomer());
    if (!empty($customer_remote_id)) {
      $default_intent_attributes['customer'] = $customer_remote_id;
    }

    $intent_array = NestedArray::mergeDeep($default_intent_attributes, $intent_attributes);

    if ($payment_method) {
      $intent_array['payment_method'] = $payment_method->getRemoteId();
    }
    elseif ($this->isReusable()) {
      $intent_array['setup_future_usage'] = $this->getPaymentMethodUsage();
    }

    foreach ($this->getOurPaymentMethodTypes() as $payment_method_type) {
      $payment_method_type->onIntentCreateAttributes($intent_array);
    }

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
      ErrorHelper::handleException($e, $payment ?? $payment_method);
    }
    return $intent;
  }

  /**
   * {@inheritdoc}
   */
  public function getApiVersion(): string {
    return $this->configuration['api_version'] ?? '2019-12-03';
  }

  /**
   * {@inheritdoc}
   */
  public function getPublishableKey(): string {
    return $this->configuration['publishable_key'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSecretKey(): string {
    return $this->configuration['secret_key'];
  }

  /**
   * {@inheritdoc}
   */
  public function getWebhookSigningSecret(): string {
    return $this->configuration['webhook_signing_secret'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getPaymentMethodUsage(): string {
    return $this->configuration['payment_method_usage'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCaptureMethod(): ?string {
    return $this->configuration['capture_method'] ?: 'automatic';
  }

  /**
   * {@inheritdoc}
   */
  public function getCheckoutFormDisplayLabel(): array {
    return $this->configuration['checkout_form_display_label'];
  }

  /**
   * Gets checkout display label.
   *
   * @return string
   *   Checkout display label.
   */
  public function getCheckoutDisplayLabel(): string {
    $display_label = '';

    $display_settings = $this->getCheckoutFormDisplayLabel();
    if (empty($display_settings['custom_label'])) {
      return $display_label;
    }

    $display_label = $display_settings['custom_label'];
    if ($display_settings['show_payment_method_logos'] === 'no') {
      return $display_label;
    }

    $logos = [
      '#theme' => 'commerce_stripe_credit_card_logos',
      '#credit_cards' => array_filter($display_settings['include_logos']),
    ];
    $before_logos = $after_logos = '';
    $payment_method_logos = $this->renderer->renderInIsolation($logos);
    if ($display_settings['show_payment_method_logos'] === 'before') {
      $before_logos = $payment_method_logos;
    }
    else {
      $after_logos = $payment_method_logos;
    }

    return $before_logos . $display_label . $after_logos;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormattedAddress(ProfileInterface $profile, string $type = 'billing'): ?array {
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

  /**
   * Update the webhook event status, if the module is installed.
   *
   * @param int|null $webhook_event_id
   *   The webhook event id.
   * @param string $webhook_event_status
   *   The webhook event status.
   * @param string|null $reason
   *   The reason for the status.
   * @param \Drupal\Core\Entity\EntityInterface|null $entity
   *   The related entity.
   */
  protected function updateWebhookEventStatus(?int $webhook_event_id, string $webhook_event_status, ?string $reason = NULL, ?EntityInterface $entity = NULL): void {
    if ($this->webhookEventModuleIsInstalled && ($webhook_event_id !== NULL)) {
      WebhookEvent::updateStatus($webhook_event_id, $webhook_event_status, $reason, $entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function canCapturePayment(PaymentInterface $payment): bool {
    /** @var \Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType\StripePaymentMethodTypeInterface $payment_method_type */
    $payment_method_type = $payment->getPaymentMethod()?->getType();
    return $payment_method_type->canCapturePayment($payment);
  }

  /**
   * Whether the payment gateway is configured for re-use.
   *
   * Note: some payment methods are not reusable, regardless of this setting.
   * e.g. Klarna and Affirm are single use payment methods.
   *
   * @return bool
   *   Whether the payment methods created can be reused or not.
   */
  protected function isReusable(): bool {
    return $this->getPaymentMethodUsage() !== 'single_use';
  }

  /**
   * Return the stripe payment method type definitions.
   *
   * @return array
   *   The payment method type definitions.
   */
  protected function getPaymentMethodTypeDefinitions(): array {
    $payment_method_type_definitions = $this->paymentMethodTypeManager->getDefinitions();
    $payment_method_type_definitions = array_filter($payment_method_type_definitions, static function ($plugin_id) {
      return str_starts_with($plugin_id, 'stripe_');
    }, ARRAY_FILTER_USE_KEY);
    return $payment_method_type_definitions;
  }

  /**
   * Fetch all our payment method types.
   *
   * Note: this is different that the base plugin method getPaymentMethodTypes.
   *
   * @return \Drupal\commerce_stripe\Plugin\Commerce\PaymentMethodType\StripePaymentMethodTypeInterface[]
   *   The payment method types.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  protected function getOurPaymentMethodTypes(): array {
    $payment_method_type_definitions = $this->getPaymentMethodTypeDefinitions();
    $payment_method_types = [];
    foreach ($payment_method_type_definitions as $plugin_id => $stripe_payment_method_type) {
      $payment_method_types[$plugin_id] = $this->paymentMethodTypeManager->createInstance($plugin_id);
    }
    return $payment_method_types;
  }

  /**
   * {@inheritDoc}
   */
  public function getIntent(?string $intent_id): PaymentIntent|SetupIntent|null {
    $intent = NULL;
    if (str_starts_with($intent_id, 'pi_')) {
      $intent = $this->getStripePaymentIntent($intent_id);
    }
    elseif (str_starts_with($intent_id, 'seti_')) {
      $intent = $this->getStripeSetupIntent($intent_id);
    }
    return $intent;
  }

  /**
   * Retrieve a Stripe Payment Intent by id.
   *
   * @param string $payment_intent_id
   *   The payment intent id.
   *
   * @return \Stripe\PaymentIntent|null
   *   The stripe payment intent.
   *
   * @throws \Stripe\Exception\ApiErrorException
   */
  protected function getStripePaymentIntent(string $payment_intent_id): ?PaymentIntent {
    $payment_intent = NULL;
    if (str_starts_with($payment_intent_id, 'pi_')) {
      $payment_intent = PaymentIntent::retrieve($payment_intent_id);
    }
    return $payment_intent;
  }

  /**
   * Retrieve a Stripe Setup Intent by id.
   *
   * @param string $setup_intent_id
   *   The payment intent id.
   *
   * @return \Stripe\SetupIntent|null
   *   The stripe setup intent.
   *
   * @throws \Stripe\Exception\ApiErrorException
   */
  protected function getStripeSetupIntent(string $setup_intent_id): ?SetupIntent {
    $setup_intent = NULL;
    if (str_starts_with($setup_intent_id, 'seti_')) {
      $setup_intent = SetupIntent::retrieve($setup_intent_id);
    }
    return $setup_intent;
  }

  /**
   * Place the order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return string
   *   The next step id.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function placeOrder(OrderInterface $order): string {
    $this->paymentOrderUpdater->updateOrder($order);
    // Redirect to the next step after 'payment'.
    /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
    $checkout_flow = $order->get('checkout_flow')->entity;
    $checkout_flow_plugin = $checkout_flow->getPlugin();
    $step_id = $checkout_flow_plugin->getNextStepId('payment');
    $order->set('checkout_step', $step_id);
    if ($step_id === 'complete') {
      // Notify other modules.
      $event = new OrderEvent($order);
      $this->eventDispatcher->dispatch($event, CheckoutEvents::COMPLETION);
      if ($order->getState()->isTransitionAllowed('place')) {
        $order->getState()->applyTransitionById('place');
      }
    }
    $order->save();
    return $step_id;
  }

}
