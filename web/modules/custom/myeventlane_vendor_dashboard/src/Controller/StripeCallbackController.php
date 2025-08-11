<?php

namespace Drupal\myeventlane_vendor_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use GuzzleHttp\Client;
use Drupal\user\Entity\User;

/**
 * Handles the Stripe Connect OAuth callback.
 */
class StripeCallbackController extends ControllerBase {

  public function handleCallback(Request $request) {
    $code = $request->query->get('code');
    $state = $request->query->get('state');
    $error = $request->query->get('error');

    if ($error) {
      \Drupal::logger('stripe_connect')->warning('Stripe returned error: @error', ['@error' => $error]);
      \Drupal::messenger()->addError('Stripe connection was cancelled or failed: ' . $error);
      return new RedirectResponse('/dashboard');
    }

    if (!$code || !$state) {
      \Drupal::logger('stripe_connect')->error('Missing code or state in Stripe callback.');
      \Drupal::messenger()->addError('Stripe callback is missing required information.');
      return new RedirectResponse('/dashboard');
    }

    $decoded_state = json_decode(base64_decode($state), TRUE);
    if (!is_array($decoded_state) || empty($decoded_state['uid'])) {
      \Drupal::logger('stripe_connect')->error('Invalid or tampered state in callback: @state', ['@state' => $state]);
      \Drupal::messenger()->addError('Invalid callback state received.');
      return new RedirectResponse('/dashboard');
    }

    $uid = $decoded_state['uid'];
    $user = User::load($uid);
    if (!$user) {
      \Drupal::logger('stripe_connect')->error('User not found for UID @uid', ['@uid' => $uid]);
      \Drupal::messenger()->addError('Unable to find user account for Stripe connection.');
      return new RedirectResponse('/dashboard');
    }

    // Load keys from config.
    $config = \Drupal::config('myeventlane_vendor_dashboard.settings');
    $mode = $config->get('mode') ?? 'test';
    $client_secret = $mode === 'live' ? $config->get('secret_key_live') : $config->get('secret_key_test');

    try {
      $client = new Client();
      $response = $client->request('POST', 'https://connect.stripe.com/oauth/token', [
        'form_params' => [
          'grant_type' => 'authorization_code',
          'code' => $code,
          'client_secret' => $client_secret,
        ],
      ]);
      $body = json_decode($response->getBody(), TRUE);
    }
    catch (\Exception $e) {
      \Drupal::logger('stripe_connect')->error('Stripe token exchange failed: @error', ['@error' => $e->getMessage()]);
      \Drupal::messenger()->addError('Failed to connect to Stripe. Please try again later.');
      return new RedirectResponse('/dashboard');
    }

    $stripe_user_id = $body['stripe_user_id'] ?? NULL;

    if (!$stripe_user_id) {
      \Drupal::logger('stripe_connect')->error('Stripe returned no user ID. Response: @response', ['@response' => print_r($body, TRUE)]);
      \Drupal::messenger()->addError('Stripe did not return an account ID.');
      return new RedirectResponse('/dashboard');
    }

    // Save to vendor profile.
    $profile_storage = \Drupal::entityTypeManager()->getStorage('profile');
    $profiles = $profile_storage->loadByProperties([
      'uid' => $uid,
      'type' => 'vendor_profile',
    ]);
    $profile = reset($profiles);
    if (!$profile) {
      $profile = $profile_storage->create([
        'uid' => $uid,
        'type' => 'vendor_profile',
        'status' => TRUE,
      ]);
    }

    $profile->set('field_stripe_account_id', $stripe_user_id);
    $profile->save();

    \Drupal::logger('stripe_connect')->notice('Stripe account connected for UID @uid: @acct', [
      '@uid' => $uid,
      '@acct' => $stripe_user_id,
    ]);

      \Drupal::messenger()->addStatus('✅ Stripe account connected successfully.');
      \Drupal::messenger()->addError('No vendor profile found to save Stripe account.');
    

    return new RedirectResponse('/dashboard');
  }

}
