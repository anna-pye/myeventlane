<?php

namespace Drupal\myeventlane_vendor_dashboard\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class StripeSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['myeventlane_vendor_dashboard.settings'];
  }

  public function getFormId() {
    return 'myeventlane_vendor_dashboard_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('myeventlane_vendor_dashboard.settings');

    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Stripe Mode'),
      '#options' => ['test' => 'Test', 'live' => 'Live'],
      '#default_value' => $config->get('mode') ?? 'test',
    ];

    $form['client_id_test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test Client ID'),
      '#default_value' => $config->get('client_id_test'),
    ];

    $form['secret_key_test'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Test Secret Key'),
      '#default_value' => $config->get('secret_key_test'),
    ];

    $form['client_id_live'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Live Client ID'),
      '#default_value' => $config->get('client_id_live'),
    ];

    $form['secret_key_live'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Live Secret Key'),
      '#default_value' => $config->get('secret_key_live'),
    ];

    $form['redirect_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirect URI'),
      '#default_value' => $config->get('redirect_uri'),
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('myeventlane_vendor_dashboard.settings')
      ->set('mode', $form_state->getValue('mode'))
      ->set('client_id_test', $form_state->getValue('client_id_test'))
      ->set('secret_key_test', $form_state->getValue('secret_key_test'))
      ->set('client_id_live', $form_state->getValue('client_id_live'))
      ->set('secret_key_live', $form_state->getValue('secret_key_live'))
      ->set('redirect_uri', $form_state->getValue('redirect_uri'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
