<?php

namespace Drupal\commerce_stripe\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a configuration form for Stripe settings.
 */
class StripeSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['commerce_stripe.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'commerce_stripe_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('commerce_stripe.settings');

    $form['load_on_every_page'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Load the Stripe script on every page. This allows Stripe to <a target="_blank" href="https://docs.stripe.com/js/including">better detect possible suspicious user behavior</a>.'),
      '#default_value' => $config->get('load_on_every_page') ?? FALSE,
    ];

    $form['collect_user_fraud_signals'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collect user interaction signals required for advanced fraud detection.'),
      '#description' => $this->t('Learn more about this feature in the <a href="https://stripe.com/docs/disputes/prevention/advanced-fraud-detection">Stripe documentation</a>.'),
      '#default_value' => $config->get('collect_user_fraud_signals') ?? TRUE,
    ];

    $form['link_payments_remote_id'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Link a Stripe payment's Remote ID in the order payments tab to the related payment in the Stripe dashboard."),
      '#default_value' => $config->get('link_payments_remote_id') ?? FALSE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('commerce_stripe.settings');
    $config
      ->set('load_on_every_page', $form_state->getValue('load_on_every_page'))
      ->set('collect_user_fraud_signals', $form_state->getValue('collect_user_fraud_signals'))
      ->set('link_payments_remote_id', $form_state->getValue('link_payments_remote_id'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

}
