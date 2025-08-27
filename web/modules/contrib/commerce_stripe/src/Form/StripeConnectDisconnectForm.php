<?php

namespace Drupal\commerce_stripe\Form;

use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Drupal\commerce_stripe\StripeHelper;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for disconnecting from Stripe.
 */
class StripeConnectDisconnectForm extends ConfirmFormBase {

  /**
   * The client factory.
   *
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $clientFactory;

  /**
   * The payment gateway.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface
   */
  protected $paymentGateway;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->clientFactory = $container->get('http_client_factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to disconnect from Stripe?');
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
  public function getFormId() {
    return 'commerce_stripe_connect_disconnect_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?PaymentGatewayInterface $commerce_payment_gateway = NULL) {
    $this->paymentGateway = $commerce_payment_gateway;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!StripeHelper::isStripeGateway($this->paymentGateway->getPlugin())) {
      $form_state->setError($form, $this->t('The provided payment gateway is not supported.'));
    }
    else {
      $configuration = $this->paymentGateway->getPluginConfiguration();
      if (empty($configuration['stripe_user_id'])) {
        $form_state->setError($form, $this->t('Unknown Stripe User ID. Cannot disconnect from Drupal Commerce.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $configuration = $this->paymentGateway->getPluginConfiguration();
    $client = $this->clientFactory->fromOptions();
    try {
      $response = $client->post(StripeHelper::BASE_CONNECT_URL . '/oauth/deauthorize', [
        'form_params' => [
          'stripe_user_id' => $configuration['stripe_user_id'],
        ],
      ]);
      $response = Json::decode($response->getBody()->getContents());

      if (!empty($response['success'])) {
        unset($configuration['stripe_user_id'], $configuration['access_token'], $configuration['publishable_key']);
        $this
          ->paymentGateway
          ->setPluginConfiguration($configuration)
          ->disable()
          ->save();
        $this->messenger()->addWarning($this->t('Stripe account disconnected successfully. The payment gateway has been disabled to ensure that its associated payment methods are no longer available at checkout.'));
      }
      else {
        $this->messenger()
          ->addError($this->t('There was an error disconnecting from Stripe.'));
      }
    }
    catch (\Exception $e) {
      $this->messenger()
        ->addError($this->t('There was an error disconnecting from Stripe.'));
    }

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
