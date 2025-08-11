<?php

namespace Drupal\myeventlane_vendor_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\profile\Entity\Profile;
use Drupal\user\Entity\User;
use Drupal\Core\Render\Markup;

class StripeConnectController extends ControllerBase {

  /**
   * Placeholder for Stripe Connect page.
   */
  public function placeholder() {
    return [
      '#markup' => '<p>This is where Stripe Connect onboarding will go. Coming soon!</p>',
    ];
  }

  /**
   * Optional: Unused dashboard controller (already handled via Twig + preprocess).
   */
  public function dashboard() {
    $user = $this->currentUser();
    $uid = $user->id();

    // Load vendor profile.
    $profile_storage = \Drupal::entityTypeManager()->getStorage('profile');
    $profiles = $profile_storage->loadByProperties([
      'uid' => $uid,
      'type' => 'vendor_profile',
    ]);
    $profile = reset($profiles);

    $description = $profile ? $profile->get('field_vendor_description')->value : 'No profile yet.';
    $logo_url = '';
    if ($profile && !$profile->get('field_profile_image')->isEmpty()) {
      $logo_url = $profile->get('field_profile_image')->entity->url();
    }

    // Load store.
    $store_storage = \Drupal::entityTypeManager()->getStorage('commerce_store');
    $stores = $store_storage->loadByProperties(['uid' => $uid]);
    $store = reset($stores);

    return [
      '#theme' => 'vendor_dashboard',
      '#store' => $store,
      '#profile_description' => Markup::create($description),
      '#logo_url' => $logo_url,
      '#attached' => [
        'library' => ['myeventlane_vendor/dashboard'],
      ],
    ];
  }

}
