<?php

namespace Drupal\myeventlane_rsvp\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class RsvpSettingsForm extends ConfigFormBase {
  protected function getEditableConfigNames() {
    return ['myeventlane_rsvp.settings'];
  }

  public function getFormId() {
    return 'myeventlane_rsvp_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $cfg = $this->config('myeventlane_rsvp.settings');
    $form['thank_you'] = [
      '#type' => 'details',
      '#title' => $this->t('Thank You redirect'),
      '#open' => TRUE,
    ];
    $form['thank_you']['use_event_field'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use per‑event field if present'),
      '#default_value' => $cfg->get('thank_you.use_event_field') ?? TRUE,
    ];
    $form['thank_you']['event_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event field machine name'),
      '#default_value' => $cfg->get('thank_you.event_field') ?? 'field_thank_you_path',
      '#description' => $this->t('If the Event has this field and it contains a path or URL, it will override the default.'),
    ];
    $form['thank_you']['default_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default Thank You path/URL'),
      '#default_value' => $cfg->get('thank_you.default_path') ?? '/thanks',
      '#description' => $this->t('Internal path (e.g., /thanks) or absolute URL.'),
      '#required' => TRUE,
    ];
    $form['thank_you']['keep_message'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Keep success message after redirect'),
      '#default_value' => $cfg->get('thank_you.keep_message') ?? TRUE,
    ];
    $form['utm'] = [
      '#type' => 'details',
      '#title' => $this->t('UTM tagging'),
      '#open' => FALSE,
    ];
    $form['utm']['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable UTM parameters'),
      '#default_value' => $cfg->get('thank_you.utm.enabled') ?? TRUE,
    ];
    $form['utm']['source'] = [
      '#type' => 'textfield',
      '#title' => $this->t('utm_source'),
      '#default_value' => $cfg->get('thank_you.utm.source') ?? 'myeventlane',
    ];
    $form['utm']['medium'] = [
      '#type' => 'textfield',
      '#title' => $this->t('utm_medium'),
      '#default_value' => $cfg->get('thank_you.utm.medium') ?? 'rsvp',
    ];
    $form['utm']['campaign'] = [
      '#type' => 'textfield',
      '#title' => $this->t('utm_campaign'),
      '#default_value' => $cfg->get('thank_you.utm.campaign') ?? 'event_{nid}',
      '#description' => $this->t('You can use {nid} token.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('myeventlane_rsvp.settings')
      ->set('thank_you.use_event_field', (bool) $form_state->getValue('use_event_field'))
      ->set('thank_you.event_field', (string) $form_state->getValue('event_field'))
      ->set('thank_you.default_path', (string) $form_state->getValue('default_path'))
      ->set('thank_you.keep_message', (bool) $form_state->getValue('keep_message'))
      ->set('thank_you.utm.enabled', (bool) $form_state->getValue(['enabled']))
      ->set('thank_you.utm.source', (string) $form_state->getValue(['source']))
      ->set('thank_you.utm.medium', (string) $form_state->getValue(['medium']))
      ->set('thank_you.utm.campaign', (string) $form_state->getValue(['campaign']))
      ->save();
    parent::submitForm($form, $form_state);
  }
}
