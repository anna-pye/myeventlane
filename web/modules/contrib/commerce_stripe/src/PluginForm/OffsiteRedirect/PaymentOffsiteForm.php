<?php

namespace Drupal\commerce_stripe\PluginForm\OffsiteRedirect;

use Drupal\commerce\Response\NeedsRedirectException;
use Drupal\commerce_payment\PluginForm\PaymentOffsiteForm as BasePaymentOffsiteForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;

/**
 * Implements the Payment offsite form.
 *
 * This form should not normally be encountered.
 */
class PaymentOffsiteForm extends BasePaymentOffsiteForm {

  use MessengerTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\commerce\Response\NeedsRedirectException
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = $this->entity;
    $order = $payment->getOrder();

    $this->messenger()->addMessage($this->t('Payment failed. Please review your information and try again.'), 'error');
    $redirect_url = Url::fromRoute('commerce_checkout.form', [
      'commerce_order' => $order?->id(),
      'step' => 'review',
    ])->toString();
    throw new NeedsRedirectException($redirect_url);
  }

}
