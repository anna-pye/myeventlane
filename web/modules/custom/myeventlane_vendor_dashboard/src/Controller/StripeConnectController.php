<?php

namespace Drupal\myeventlane_vendor_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;

/**
 * Handles Stripe Connect redirection.
 */
class StripeConnectController extends ControllerBase {

//   /**
//  * Placeholder method to prevent uninstall errors.
//  */
// public function placeholder() {
//   return [
//     '#markup' => 'Temporary placeholder route to fix uninstall issue.',
//   ];
// }

  /**
   * Redirects the vendor to Stripe's OAuth page.
   */
  public function redirectToStripe() {
    $config = $this->config('myeventlane_vendor_dashboard.settings');

    $mode = $config->get('mode') ?? 'test';
    $client_id = $mode === 'live' ? $config->get('client_id_live') : $config->get('client_id_test');
    $redirect_uri = $config->get('redirect_uri');

    // Encode user ID in state for later reference
    $user = $this->currentUser();
    $state = base64_encode(json_encode(['uid' => $user->id()]));

    // Build Stripe OAuth URL
    $stripe_url = Url::fromUri('https://connect.stripe.com/oauth/authorize', [
      'query' => [
        'response_type' => 'code',
        'client_id' => $client_id,
        'scope' => 'read_write',
        'redirect_uri' => $redirect_uri,
        'state' => $state,
      ],
    ])->toString();

    return new TrustedRedirectResponse($stripe_url);
  }

}

