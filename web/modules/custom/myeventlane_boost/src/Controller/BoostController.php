<?php

namespace Drupal\myeventlane_boost\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\node\Entity\Node;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Url;

class BoostController extends ControllerBase {

  /**
   * Boost page for vendors.
   */
  public function boostPage($node) {
    $account = $this->currentUser();
    $event = Node::load($node);

    // Only event owner or admin can boost.
    if (!$event || $event->bundle() !== 'event' || $event->getOwnerId() != $account->id()) {
      return ['#markup' => $this->t('You do not have access to boost this event.')];
    }

    // If already boosted and not expired, show status.
    $expiry_value = $event->get('field_promo_expiry')->value;
    if ($event->get('field_promoted')->value && $expiry_value && strtotime($expiry_value) > time()) {
      $expiry = \Drupal::service('date.formatter')->format(strtotime($expiry_value), 'custom', 'j M Y, g:ia');
      return [
        '#theme' => 'boost_event_page',
        '#event' => $event,
        '#message' => $this->t('This event is currently boosted until @expiry.', ['@expiry' => $expiry]),
      ];
    }


    // Show boost purchase form.
    $form = \Drupal::formBuilder()->getForm('\Drupal\myeventlane_boost\Form\BoostPurchaseForm', $event);
    return [
      '#theme' => 'boost_event_page',
      '#event' => $event,
      '#purchase_form' => $form,
    ];
  }
}
