<?php

namespace Drupal\social_auth_apple\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\social_auth\Form\SocialAuthSettingsForm;
use Drupal\social_auth\Plugin\Network\NetworkInterface;
use Drupal\social_auth_apple\Settings\AppleAuthSettingsInterface;

/**
 * Settings form for Social Auth Apple.
 */
class AppleAuthSettingsForm extends SocialAuthSettingsForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'social_auth_apple_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    $configs = parent::getEditableConfigNames();
    $configs[] = AppleAuthSettingsInterface::CONFIG_NAME;
    return $configs;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NetworkInterface $network = NULL): array {
    $form = parent::buildForm($form, $form_state, $network);
    $form['network']['client_id']['#description'] = $this->t('Copy the Client ID here, it is the Service ID');
    $form['network']['client_secret']['#access'] = FALSE;
    $form['network']['authorized_redirect_url']['#description'] = $this->t('Copy this value to <em>Authorized redirect URIs</em> field of your Apple Service settings.');
    $form['network']['authorized_redirect_url']['#weight'] = 99;
    $form['network']['advanced']['#weight'] = 100;

    $config = $this->config(AppleAuthSettingsInterface::CONFIG_NAME);
    $form['network']['team_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Team ID'),
      '#default_value' => $config->get('team_id'),
      '#required' => TRUE,
      '#description' => $this->t('Copy the Team ID here (10 characters top right under the login)'),
    ];
    $form['network']['key_file_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key file ID'),
      '#default_value' => $config->get('key_file_id'),
      '#required' => TRUE,
      '#description' => $this->t('Copy key file ID here (prefix of the key file)'),
    ];
    $form['network']['key_file_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key file path'),
      '#default_value' => $config->get('key_file_path'),
      '#required' => TRUE,
      '#description' => $this->t('Path to the key file relative to the website root. (f.ex. keys/HGNHTBYZB7.p8)'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if ($form_state->getErrors()) {
      return;
    }

    $key_file_path = $form_state->getValue('key_file_path');
    if (!file_exists($key_file_path)) {
      $form_state->setErrorByName('key_file_path', $this->t('Key file does not exist.'));
    }
    else {
      $key_content = file_get_contents($key_file_path);
      if (!str_contains($key_content, '-----BEGIN PRIVATE KEY-----') || !str_contains($key_content, '-----END PRIVATE KEY-----')) {
        $form_state->setErrorByName('key_file_path', $this->t('Key file is not a valid private key.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
    $values = $form_state->getValues();
    $this->config(AppleAuthSettingsInterface::CONFIG_NAME)
      ->set('team_id', $values['team_id'])
      ->set('key_file_id', $values['key_file_id'])
      ->set('key_file_path', $values['key_file_path'])
      ->clear('client_secret')
      ->save();
  }

}
