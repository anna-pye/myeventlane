<?php

namespace Drupal\commerce_stripe\PluginForm\Stripe;

use Drupal\commerce_payment\PluginForm\PaymentMethodAddForm as BasePaymentMethodAddForm;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\user\UserInterface;
use Stripe\SetupIntent;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides payment form for Stripe.
 */
class PaymentMethodAddForm extends BasePaymentMethodAddForm implements TrustedCallbackInterface {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    $instance = parent::create($container);
    $instance->routeMatch = $container->get('current_route_match');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildCreditCardForm(array $element, FormStateInterface $form_state): array {
    // Alter the form with Stripe specific needs.
    $element['#attributes']['class'][] = 'stripe-form';

    /** @var \Drupal\commerce_stripe\Plugin\Commerce\PaymentGateway\StripeInterface $plugin */
    $plugin = $this->plugin;

    /** @var \Drupal\commerce_payment\Entity\PaymentMethodInterface $payment_method */
    $payment_method = $this->entity;
    $payment_method_owner = $payment_method->getOwner();
    // @todo Replace with setting check from https://www.drupal.org/project/commerce/issues/2871483.
    // @todo Simplify check after https://www.drupal.org/project/commerce/issues/3073942.
    $client_secret = NULL;
    if ($payment_method_owner instanceof UserInterface && $payment_method_owner->isAuthenticated()) {
      // @todo Use context passed by parent element after https://www.drupal.org/project/commerce/issues/3077783.
      if ($this->routeMatch->getRouteName() === 'entity.commerce_payment_method.add_form') {
        // A SetupIntent is required if this is being created for off-session
        // usage (for instance, outside of checkout where there is no payment
        // intent that will be authenticated.)
        $setup_intent = SetupIntent::create([
          'usage' => 'off_session',
        ]);
        $client_secret = $setup_intent->client_secret;
      }
    }

    $element['#attached']['library'][] = 'commerce_stripe/form';
    $element['#attached']['drupalSettings']['commerceStripe'] = [
      'publishableKey' => $plugin->getPublishableKey(),
      'clientSecret' => $client_secret,
      'enable_credit_card_logos' => FALSE,
    ];

    // Populated by the JS library.
    $element['stripe_payment_method_id'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => 'stripe-payment-method-id',
      ],
    ];

    // Display credit card logos in checkout form.
    if ($plugin->getConfiguration()['enable_credit_card_icons']) {
      $element['#attached']['library'][] = 'commerce_stripe/credit_card_icons';
      $element['#attached']['drupalSettings']['commerceStripe']['enable_credit_card_logos'] = TRUE;

      $supported_credit_cards = [];
      foreach ($plugin->getCreditCardTypes() as $credit_card) {
        $supported_credit_cards[] = $credit_card->getId();
      }

      $element['credit_card_logos'] = [
        '#theme' => 'commerce_stripe_credit_card_logos',
        '#credit_cards' => $supported_credit_cards,
      ];
    }

    $element['card_number'] = [
      '#type' => 'item',
      '#title' => t('Card number'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#markup' => '<div id="card-number-element" class="form-text"></div>',
    ];

    $element['expiration'] = [
      '#type' => 'item',
      '#title' => t('Expiration date'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#markup' => '<div id="expiration-element"></div>',
    ];

    $element['security_code'] = [
      '#type' => 'item',
      '#title' => t('CVC'),
      '#required' => TRUE,
      '#validated' => TRUE,
      '#markup' => '<div id="security-code-element"></div>',
    ];

    // To display validation errors.
    $element['payment_errors'] = [
      '#type' => 'markup',
      '#markup' => '<div id="payment-errors"></div>',
      '#weight' => -200,
    ];

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($this->entity);
    $cacheability->setCacheMaxAge(0);
    $cacheability->applyTo($element);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function validateCreditCardForm(array &$element, FormStateInterface $form_state): void {
    // The JS library performs its own validation.
  }

  /**
   * {@inheritdoc}
   */
  public function submitCreditCardForm(array $element, FormStateInterface $form_state): void {
    if ($email = $form_state->getValue(['contact_information', 'email'])) {
      $email_parents = array_merge($element['#parents'], ['email']);
      $form_state->setValue($email_parents, $email);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    if (isset($form['billing_information'])) {
      $form['billing_information']['#after_build'][] = [get_class($this), 'addAddressAttributes'];
    }

    return $form;
  }

  /**
   * Element #after_build callback: adds "data-stripe" to address properties.
   *
   * This allows our JavaScript to pass these values to Stripe as customer
   * information, enabling CVC, Zip, and Street checks.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The modified form element.
   */
  public static function addAddressAttributes(array $element, FormStateInterface $form_state): array {
    if (isset($element['address'])) {
      $field_attribute_map = [
        'given_name' => 'name.1',
        'family_name' => 'name.2',
        'address_line1' => 'address.line1',
        'address_line2' => 'address.line2',
        'locality' => 'address.city',
        'administrative_area' => 'address.state',
        'postal_code' => 'address.postal_code',
      ];
      foreach ($field_attribute_map as $field_name => $attribute_value) {
        if (!empty($element['address']['widget'][0]['address'][$field_name])) {
          $element['address']['widget'][0]['address'][$field_name]['#attributes']['data-stripe'] = $attribute_value;
        }
      }
      // Country code is a sub-element and needs another callback.
      if (!empty($element['address']['widget'][0]['address']['country_code'])) {
        $element['address']['widget'][0]['address']['country_code']['#pre_render'][] = [
          static::class,
          'addCountryCodeAttributes',
        ];
      }
    }

    return $element;
  }

  /**
   * Element #pre_render callback: adds "data-stripe" to the country_code.
   *
   * This ensures data-stripe is on the hidden or select element for the country
   * code, so that it is properly passed to Stripe.
   *
   * @param array $element
   *   The form element.
   *
   * @return array
   *   The modified form element.
   */
  public static function addCountryCodeAttributes(array $element): array {
    $element['country_code']['#attributes']['data-stripe'] = 'address.country';
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return [
      'addCountryCodeAttributes',
    ];
  }

}
