<?php

namespace Drupal\myeventlane_vendor_dashboard\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class VendorUpgradeController extends ControllerBase {

  public function becomeVendor() {
    $user = $this->currentUser();
    $account = \Drupal\user\Entity\User::load($user->id());

    if ($account && !$account->hasRole('vendor')) {
      $account->addRole('vendor');
      $account->save();
      \Drupal::messenger()->addMessage('You are now a vendor! Welcome aboard.');
    }
    else {
      \Drupal::messenger()->addWarning('You are already a vendor.');
    }

    return new RedirectResponse(Url::fromRoute('<front>')->toString()); // or '/dashboard'
  }
}
