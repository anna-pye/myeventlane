<?php

namespace Drupal\commerce_stripe\Form;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_stripe\StripeHelper;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a Stripe connect form.
 */
class StripeConnectConfirmationForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_stripe_connect_confirmation_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?PaymentGatewayInterface $commerce_payment_gateway = NULL) {
    $form = parent::buildForm($form, $form_state);

    $form['#attached']['library'][] = 'commerce_stripe/connect_buttons';
    $oauth_return = Url::fromRoute('commerce_stripe.connect.oauth_return', [
      'commerce_payment_gateway' => $commerce_payment_gateway->id(),
    ], ['absolute' => TRUE])->toString();
    $stripe_connect_url_string = sprintf('%s/oauth/authorize?mode=%s&redirect_uri=%s', StripeHelper::BASE_CONNECT_URL, $commerce_payment_gateway->getPlugin()->getMode(), $oauth_return);
    $stripe_connect_url = Url::fromUri($stripe_connect_url_string, [
      'absolute' => TRUE,
    ]);
    $form['actions']['submit'] = [
      '#type' => 'inline_template',
      '#template' => '<a href="{{ url }}" class="button button--primary stripe-connect"><span>{{ connect }}</span></a>',
      '#context' => [
        'connect' => $this->t('Connect with'),
        'url' => $stripe_connect_url->toString(),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to connect with Stripe?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return Url::fromRoute('entity.commerce_payment_gateway.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
